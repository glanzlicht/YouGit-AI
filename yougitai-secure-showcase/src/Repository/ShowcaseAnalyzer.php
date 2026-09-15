<?php
namespace YougitAI\SecureShowcase\Repository;

final class ShowcaseAnalyzer {
    public function analyze( array $repository, array $files ): array {
        $languages = [];
        $directories = [];
        $bytes = 0;
        $redacted = 0;
        $readme = null;
        $manifest_names = [];
        $notable = [];

        foreach ( $files as $file ) {
            $path = (string) $file['path'];
            $language = (string) ( $file['language'] ?: __( 'Other', 'yougitai-secure-showcase' ) );
            $languages[ $language ] = ( $languages[ $language ] ?? 0 ) + max( 1, (int) $file['size'] );
            $bytes += (int) $file['size'];
            if ( in_array( (string) $file['visibility'], [ 'redacted', 'hidden' ], true ) ) $redacted++;
            $parts = explode( '/', $path );
            if ( count( $parts ) > 1 && $parts[0] !== '' ) $directories[ $parts[0] ] = ( $directories[ $parts[0] ] ?? 0 ) + 1;
            $base = strtolower( basename( $path ) );
            if ( in_array( $base, [ 'package.json', 'composer.json', 'pubspec.yaml', 'requirements.txt', 'pyproject.toml', 'cargo.toml', 'go.mod', 'pom.xml', 'build.gradle', 'dockerfile', 'docker-compose.yml', 'docker-compose.yaml' ], true ) ) $manifest_names[] = $base;
            if ( count( $notable ) < 8 && preg_match( '#(^|/)(src|app|lib|packages|modules|services|api|frontend|backend)(/|$)#i', $path ) ) $notable[] = $path;
            if ( $readme === null && preg_match( '#(^|/)readme(?:\.[a-z0-9]+)?$#i', $path ) ) $readme = $file;
        }
        arsort( $languages ); arsort( $directories );
        $total_language_bytes = max( 1, array_sum( $languages ) );
        $language_rows = [];
        foreach ( array_slice( $languages, 0, 8, true ) as $language => $value ) {
            $language_rows[] = [ 'name' => $language, 'percent' => round( ( $value / $total_language_bytes ) * 100, 1 ) ];
        }
        return [
            'summary' => $this->summary( $repository, count( $files ), count( $directories ), $language_rows ),
            'files' => count( $files ), 'bytes' => $bytes, 'redacted' => $redacted,
            'languages' => $language_rows,
            'modules' => array_slice( array_keys( $directories ), 0, 10 ),
            'manifests' => array_values( array_unique( $manifest_names ) ),
            'tech_stack' => $this->tech_stack( $language_rows, $manifest_names ),
            'architecture' => $this->architecture( array_keys( $directories ), $manifest_names ),
            'readme_file_id' => $readme ? (int) $readme['id'] : 0,
            'notable_files' => array_values( array_unique( $notable ) ),
        ];
    }

    private function summary( array $repository, int $files, int $modules, array $languages ): string {
        $primary = $languages[0]['name'] ?? __( 'multiple technologies', 'yougitai-secure-showcase' );
        return sprintf(
            __( '%1$s is a verified code showcase with %2$s visible project entries across %3$s top-level modules. The primary detected language is %4$s; protected files remain visible in the structure while their contents stay private.', 'yougitai-secure-showcase' ),
            (string) $repository['title'], number_format_i18n( $files ), number_format_i18n( $modules ), $primary
        );
    }

    private function tech_stack( array $languages, array $manifests ): array {
        $stack = array_map( static fn( array $row ): string => (string) $row['name'], array_slice( $languages, 0, 6 ) );
        $map = [
            'package.json' => 'Node.js / JavaScript ecosystem', 'composer.json' => 'PHP / Composer', 'pubspec.yaml' => 'Flutter / Dart',
            'requirements.txt' => 'Python', 'pyproject.toml' => 'Python', 'cargo.toml' => 'Rust', 'go.mod' => 'Go',
            'pom.xml' => 'Java / Maven', 'build.gradle' => 'Gradle', 'dockerfile' => 'Docker', 'docker-compose.yml' => 'Docker Compose', 'docker-compose.yaml' => 'Docker Compose',
        ];
        foreach ( $manifests as $manifest ) if ( isset( $map[ $manifest ] ) ) $stack[] = $map[ $manifest ];
        return array_values( array_unique( $stack ) );
    }

    private function architecture( array $directories, array $manifests ): array {
        $layers = [];
        foreach ( array_slice( $directories, 0, 8 ) as $directory ) $layers[] = [ 'label' => $directory, 'type' => 'module' ];
        if ( in_array( 'dockerfile', $manifests, true ) || in_array( 'docker-compose.yml', $manifests, true ) || in_array( 'docker-compose.yaml', $manifests, true ) ) $layers[] = [ 'label' => 'Container / deployment', 'type' => 'infrastructure' ];
        return $layers;
    }
}
