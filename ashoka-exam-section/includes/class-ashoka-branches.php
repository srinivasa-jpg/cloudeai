<?php
/**
 * Branch CRUD, import, and export.
 *
 * @package Ashoka_Exam_Section
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ashoka_Branches {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'ashoka_branches';
    }

    // ----------------------------------------------------------------
    // Admin page renderer
    // ----------------------------------------------------------------
    public static function render_page() {
        if ( ! current_user_can( 'ashoka_manage_branches' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'ashoka-exam-section' ) );
        }

        self::handle_actions();

        $action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

        if ( 'add' === $action || 'edit' === $action ) {
            self::render_form( $action );
        } else {
            self::render_list();
        }
    }

    // ----------------------------------------------------------------
    // Handle form submissions
    // ----------------------------------------------------------------
    private static function handle_actions() {
        global $wpdb;
        $table = self::table();

        // Add / Edit.
        if ( isset( $_POST['ashoka_branch_save'] ) ) {
            check_admin_referer( 'ashoka_branch_save' );
            if ( ! current_user_can( 'ashoka_manage_branches' ) ) {
                wp_die( esc_html__( 'Permission denied.', 'ashoka-exam-section' ) );
            }

            $branch_name = sanitize_text_field( wp_unslash( $_POST['branch_name'] ?? '' ) );
            if ( empty( $branch_name ) ) {
                add_settings_error( 'ashoka_branches', 'empty', __( 'Branch name is required.', 'ashoka-exam-section' ), 'error' );
                return;
            }

            $sno = isset( $_POST['sno'] ) ? absint( $_POST['sno'] ) : 0;

            if ( $sno ) {
                $wpdb->update( $table, array( 'branch_name' => $branch_name ), array( 'sno' => $sno ), array( '%s' ), array( '%d' ) );
                add_settings_error( 'ashoka_branches', 'updated', __( 'Branch updated.', 'ashoka-exam-section' ), 'updated' );
            } else {
                $wpdb->insert( $table, array( 'branch_name' => $branch_name ), array( '%s' ) );
                add_settings_error( 'ashoka_branches', 'added', __( 'Branch added.', 'ashoka-exam-section' ), 'updated' );
            }
        }

        // Delete.
        if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['sno'] ) ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Permission denied.', 'ashoka-exam-section' ) );
            }
            check_admin_referer( 'ashoka_branch_delete_' . absint( $_GET['sno'] ) );
            $wpdb->delete( $table, array( 'sno' => absint( $_GET['sno'] ) ), array( '%d' ) );
            add_settings_error( 'ashoka_branches', 'deleted', __( 'Branch deleted.', 'ashoka-exam-section' ), 'updated' );
            wp_safe_redirect( admin_url( 'admin.php?page=ashoka-branches' ) );
            exit;
        }

        // Import CSV.
        if ( isset( $_POST['ashoka_branch_import'] ) ) {
            check_admin_referer( 'ashoka_branch_import' );
            if ( ! current_user_can( 'ashoka_import_data' ) ) {
                wp_die( esc_html__( 'Permission denied.', 'ashoka-exam-section' ) );
            }
            self::import_csv();
        }

        // Export CSV.
        if ( isset( $_GET['action'] ) && 'export' === $_GET['action'] ) {
            if ( ! current_user_can( 'ashoka_export_data' ) ) {
                wp_die( esc_html__( 'Permission denied.', 'ashoka-exam-section' ) );
            }
            check_admin_referer( 'ashoka_branch_export' );
            self::export_csv();
        }
    }

    // ----------------------------------------------------------------
    // List view
    // ----------------------------------------------------------------
    private static function render_list() {
        global $wpdb;
        $table    = self::table();
        $per_page = 20;
        $paged    = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $offset   = ( $paged - 1 ) * $per_page;
        $search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

        if ( $search ) {
            $total   = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE branch_name LIKE %s", '%' . $wpdb->esc_like( $search ) . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE branch_name LIKE %s ORDER BY branch_name ASC LIMIT %d OFFSET %d", '%' . $wpdb->esc_like( $search ) . '%', $per_page, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        } else {
            $total   = $wpdb->get_var( "SELECT COUNT(*) FROM $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY branch_name ASC LIMIT %d OFFSET %d", $per_page, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        $total_pages = ceil( $total / $per_page );
        settings_errors( 'ashoka_branches' );
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Branches', 'ashoka-exam-section' ); ?></h1>
            <?php if ( current_user_can( 'ashoka_manage_branches' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ashoka-branches&action=add' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'ashoka-exam-section' ); ?></a>
            <?php endif; ?>

            <form method="get" style="float:right;margin-top:8px;">
                <input type="hidden" name="page" value="ashoka-branches">
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search branches…', 'ashoka-exam-section' ); ?>">
                <button type="submit" class="button"><?php esc_html_e( 'Search', 'ashoka-exam-section' ); ?></button>
            </form>
            <br class="clear">

            <?php if ( current_user_can( 'ashoka_export_data' ) ) : ?>
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ashoka-branches&action=export' ), 'ashoka_branch_export' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Export CSV', 'ashoka-exam-section' ); ?></a>
            <?php endif; ?>

            <?php if ( current_user_can( 'ashoka_import_data' ) ) : ?>
                <button type="button" class="button button-secondary" onclick="document.getElementById('ashoka-branch-import-form').style.display='block'"><?php esc_html_e( 'Import CSV', 'ashoka-exam-section' ); ?></button>
                <div id="ashoka-branch-import-form" style="display:none;margin-top:10px;">
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'ashoka_branch_import' ); ?>
                        <input type="file" name="import_file" accept=".csv" required>
                        <button type="submit" name="ashoka_branch_import" class="button button-primary"><?php esc_html_e( 'Upload & Import', 'ashoka-exam-section' ); ?></button>
                    </form>
                </div>
            <?php endif; ?>

            <table class="wp-list-table widefat fixed striped ashoka-table" style="margin-top:15px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'SNO', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Branch Name', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'ashoka-exam-section' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $results ) : ?>
                        <?php foreach ( $results as $row ) : ?>
                            <tr>
                                <td><?php echo esc_html( $row->sno ); ?></td>
                                <td><?php echo esc_html( $row->branch_name ); ?></td>
                                <td>
                                    <?php if ( current_user_can( 'ashoka_manage_branches' ) ) : ?>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ashoka-branches&action=edit&sno=' . $row->sno ) ); ?>"><?php esc_html_e( 'Edit', 'ashoka-exam-section' ); ?></a>
                                    <?php endif; ?>
                                    <?php if ( current_user_can( 'manage_options' ) ) : ?>
                                        &nbsp;|&nbsp;
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ashoka-branches&action=delete&sno=' . $row->sno ), 'ashoka_branch_delete_' . $row->sno ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this branch?', 'ashoka-exam-section' ); ?>')" style="color:red;"><?php esc_html_e( 'Delete', 'ashoka-exam-section' ); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="3"><?php esc_html_e( 'No branches found.', 'ashoka-exam-section' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo wp_kses_post( paginate_links( array(
                            'base'    => add_query_arg( 'paged', '%#%' ),
                            'format'  => '',
                            'current' => $paged,
                            'total'   => $total_pages,
                        ) ) );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ----------------------------------------------------------------
    // Add / Edit form
    // ----------------------------------------------------------------
    private static function render_form( $action ) {
        global $wpdb;
        $table = self::table();
        $sno   = isset( $_GET['sno'] ) ? absint( $_GET['sno'] ) : 0;
        $row   = $sno ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE sno = %d", $sno ) ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        settings_errors( 'ashoka_branches' );
        ?>
        <div class="wrap">
            <h1><?php echo 'edit' === $action ? esc_html__( 'Edit Branch', 'ashoka-exam-section' ) : esc_html__( 'Add Branch', 'ashoka-exam-section' ); ?></h1>
            <form method="post">
                <?php wp_nonce_field( 'ashoka_branch_save' ); ?>
                <?php if ( $sno ) : ?>
                    <input type="hidden" name="sno" value="<?php echo esc_attr( $sno ); ?>">
                <?php endif; ?>
                <table class="form-table">
                    <tr>
                        <th><label for="branch_name"><?php esc_html_e( 'Branch Name', 'ashoka-exam-section' ); ?></label></th>
                        <td><input type="text" id="branch_name" name="branch_name" class="regular-text" value="<?php echo $row ? esc_attr( $row->branch_name ) : ''; ?>" required></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" name="ashoka_branch_save" class="button button-primary"><?php esc_html_e( 'Save', 'ashoka-exam-section' ); ?></button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ashoka-branches' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'ashoka-exam-section' ); ?></a>
                </p>
            </form>
        </div>
        <?php
    }

    // ----------------------------------------------------------------
    // CSV export
    // ----------------------------------------------------------------
    private static function export_csv() {
        global $wpdb;
        $table   = self::table();
        $results = $wpdb->get_results( "SELECT * FROM $table ORDER BY sno ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=branches-' . gmdate( 'Y-m-d' ) . '.csv' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'SNO', 'Branch Name' ) );
        foreach ( $results as $row ) {
            fputcsv( $out, array( $row['sno'], $row['branch_name'] ) );
        }
        fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        exit;
    }

    // ----------------------------------------------------------------
    // CSV import
    // ----------------------------------------------------------------
    private static function import_csv() {
        global $wpdb;
        $table = self::table();

        if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
            add_settings_error( 'ashoka_branches', 'no_file', __( 'Please select a CSV file.', 'ashoka-exam-section' ), 'error' );
            return;
        }

        $file = fopen( sanitize_text_field( wp_unslash( $_FILES['import_file']['tmp_name'] ) ), 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        if ( ! $file ) {
            add_settings_error( 'ashoka_branches', 'open_fail', __( 'Cannot open CSV file.', 'ashoka-exam-section' ), 'error' );
            return;
        }

        $header = fgetcsv( $file );
        $count  = 0;
        while ( ( $row = fgetcsv( $file ) ) !== false ) {
            if ( empty( $row[1] ) ) {
                continue;
            }
            $branch_name = sanitize_text_field( $row[1] );
            $existing    = $wpdb->get_var( $wpdb->prepare( "SELECT sno FROM $table WHERE branch_name = %s", $branch_name ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ( ! $existing ) {
                $wpdb->insert( $table, array( 'branch_name' => $branch_name ), array( '%s' ) );
                $count++;
            }
        }
        fclose( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        add_settings_error( 'ashoka_branches', 'imported', sprintf( __( 'Imported %d branch(es).', 'ashoka-exam-section' ), $count ), 'updated' );
    }
}
