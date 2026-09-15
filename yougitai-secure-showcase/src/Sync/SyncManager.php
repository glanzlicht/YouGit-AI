<?php
namespace YougitAI\SecureShowcase\Sync;

use YougitAI\SecureShowcase\Audit\Logger;
use YougitAI\SecureShowcase\Repository\RepositoryService;

final class SyncManager {
    public const HOOK = 'yougitai_ss_sync_repository';

    public function register(): void {
        add_action( self::HOOK, [ $this, 'run' ], 10, 1 );
    }

    public function enqueue( int $repository_id ): bool {
        if ( $repository_id < 1 ) return false;
        if ( function_exists( 'as_next_scheduled_action' ) && function_exists( 'as_enqueue_async_action' ) ) {
            if ( ! as_next_scheduled_action( self::HOOK, [ $repository_id ], 'yougitai-secure-showcase' ) ) {
                as_enqueue_async_action( self::HOOK, [ $repository_id ], 'yougitai-secure-showcase' );
            }
            return true;
        }
        if ( ! wp_next_scheduled( self::HOOK, [ $repository_id ] ) ) {
            return (bool) wp_schedule_single_event( time() + 5, self::HOOK, [ $repository_id ] );
        }
        return true;
    }

    public function configure_schedule( int $repository_id, string $mode ): void {
        $this->clear_schedule( $repository_id );
        if ( ! in_array( $mode, [ 'hourly', 'daily' ], true ) ) return;
        wp_schedule_event( time() + 60, $mode, self::HOOK, [ $repository_id ] );
    }

    public function clear_schedule( int $repository_id ): void {
        $timestamp = wp_next_scheduled( self::HOOK, [ $repository_id ] );
        while ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::HOOK, [ $repository_id ] );
            $timestamp = wp_next_scheduled( self::HOOK, [ $repository_id ] );
        }
    }

    public function run( int $repository_id ): void {
        $service = new RepositoryService();
        $result = $service->import_snapshot( $repository_id );
        $logger = new Logger();
        if ( is_wp_error( $result ) ) {
            $logger->log( 'sync_failed', __( 'Automatic repository sync failed.', 'yougitai-secure-showcase' ), $repository_id, null, [ 'error' => $result->get_error_code() ] );
            return;
        }
        $logger->log( 'sync_completed', __( 'Automatic repository sync completed.', 'yougitai-secure-showcase' ), $repository_id, (int) $result['snapshot_id'] );
    }
}
