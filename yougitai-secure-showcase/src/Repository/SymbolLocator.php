<?php
namespace YougitAI\SecureShowcase\Repository;

final class SymbolLocator {
    /**
     * Locate one class/function/method by symbol name and return byte offsets.
     * This intentionally supports a conservative subset. Ambiguous or unsupported
     * source returns null so callers can fail closed.
     *
     * @return array{start:int,end:int,signature:string,kind:string}|null
     */
    public function locate( string $path, string $content, string $symbol ): ?array {
        $symbol = trim( $symbol );
        if ( $symbol === '' || $content === '' ) {
            return null;
        }

        $ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
        if ( $ext === 'py' ) {
            return $this->locate_python( $content, $symbol );
        }

        if ( in_array( $ext, [ 'php', 'js', 'jsx', 'ts', 'tsx', 'dart', 'java', 'kt', 'swift', 'go', 'rs', 'cs', 'c', 'h', 'cpp', 'hpp' ], true ) ) {
            return $this->locate_braced( $content, $symbol );
        }

        return null;
    }

    /** @return array{start:int,end:int,signature:string,kind:string}|null */
    private function locate_braced( string $content, string $symbol ): ?array {
        $name = preg_quote( $symbol, '/' );
        $patterns = [
            'class' => '/(?:^|\n)[\t ]*(?:export[\t ]+)?(?:abstract[\t ]+|final[\t ]+)?class[\t ]+' . $name . '\b[^\{]*\{/mi',
            'function' => '/(?:^|\n)[\t ]*(?:(?:public|protected|private|static|async|final|abstract|export|const|inline|virtual|override|suspend)[\t ]+)*(?:function[\t ]+)?' . $name . '[\t ]*\([^\n\{;]*\)[^\{;]*\{/mi',
        ];

        $matches = [];
        foreach ( $patterns as $kind => $pattern ) {
            if ( preg_match_all( $pattern, $content, $found, PREG_OFFSET_CAPTURE ) ) {
                foreach ( $found[0] as $m ) {
                    $matches[] = [ 'kind' => $kind, 'text' => $m[0], 'offset' => $m[1] ];
                }
            }
        }
        if ( count( $matches ) !== 1 ) {
            return null;
        }

        $match = $matches[0];
        $brace = strpos( $content, '{', $match['offset'] );
        if ( $brace === false ) {
            return null;
        }
        $end = $this->matching_brace( $content, $brace );
        if ( $end === null ) {
            return null;
        }

        $start = $match['offset'];
        if ( $start > 0 && $content[ $start ] === "\n" ) {
            $start++;
        }
        $signature = trim( substr( $content, $start, $brace - $start ) );
        return [ 'start' => $start, 'end' => $end + 1, 'signature' => $signature, 'kind' => $match['kind'] ];
    }

    private function matching_brace( string $content, int $open ): ?int {
        $depth = 0;
        $quote = null;
        $escaped = false;
        $length = strlen( $content );
        for ( $i = $open; $i < $length; $i++ ) {
            $ch = $content[ $i ];
            if ( $quote !== null ) {
                if ( $escaped ) {
                    $escaped = false;
                    continue;
                }
                if ( $ch === '\\' ) {
                    $escaped = true;
                    continue;
                }
                if ( $ch === $quote ) {
                    $quote = null;
                }
                continue;
            }
            if ( $ch === '"' || $ch === "'" || $ch === '`' ) {
                $quote = $ch;
                continue;
            }
            if ( $ch === '{' ) {
                $depth++;
            } elseif ( $ch === '}' ) {
                $depth--;
                if ( $depth === 0 ) {
                    return $i;
                }
            }
        }
        return null;
    }

    /** @return array{start:int,end:int,signature:string,kind:string}|null */
    private function locate_python( string $content, string $symbol ): ?array {
        $name = preg_quote( $symbol, '/' );
        $pattern = '/^(?<indent>[\t ]*)(?<kind>class|def|async[\t ]+def)[\t ]+' . $name . '\b[^\n]*:/mi';
        if ( preg_match_all( $pattern, $content, $found, PREG_OFFSET_CAPTURE ) !== 1 ) {
            return null;
        }
        $start = $found[0][0][1];
        $header = $found[0][0][0];
        $indent = strlen( str_replace( "\t", '    ', $found['indent'][0][0] ) );
        $header_end = $start + strlen( $header );
        $line_end = strpos( $content, "\n", $header_end );
        if ( $line_end === false ) {
            $line_end = strlen( $content );
        }
        $cursor = $line_end + 1;
        $end = strlen( $content );
        while ( $cursor < strlen( $content ) ) {
            $next = strpos( $content, "\n", $cursor );
            $next = $next === false ? strlen( $content ) : $next;
            $line = substr( $content, $cursor, $next - $cursor );
            if ( trim( $line ) !== '' ) {
                preg_match( '/^[\t ]*/', $line, $m );
                $current_indent = strlen( str_replace( "\t", '    ', $m[0] ?? '' ) );
                if ( $current_indent <= $indent ) {
                    $end = $cursor;
                    break;
                }
            }
            $cursor = $next + 1;
        }
        return [
            'start' => $start,
            'end' => $end,
            'signature' => trim( $header ),
            'kind' => str_contains( $found['kind'][0][0], 'def' ) ? 'function' : 'class',
        ];
    }
}
