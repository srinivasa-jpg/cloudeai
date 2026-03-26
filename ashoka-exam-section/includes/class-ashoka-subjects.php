<?php
/**
 * Subject CRUD, import, and export.
 *
 * @package Ashoka_Exam_Section
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ashoka_Subjects {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'ashoka_subjects';
    }

    // ----------------------------------------------------------------
    // Admin page renderer
    // ----------------------------------------------------------------
    public static function render_page() {
        if ( ! current_user_can( 'ashoka_manage_subjects' ) ) {
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

        if ( isset( $_POST['ashoka_subject_save'] ) ) {
            check_admin_referer( 'ashoka_subject_save' );
            if ( ! current_user_can( 'ashoka_manage_subjects' ) ) {
                wp_die( esc_html__( 'Permission denied.', 'ashoka-exam-section' ) );
            }

            $sub_name = sanitize_text_field( wp_unslash( $_POST['sub_name'] ?? '' ) );
            $sub_code = sanitize_text_field( wp_unslash( $_POST['sub_code'] ?? '' ) );
            $credits  = isset( $_POST['credits'] ) ? floatval( $_POST['credits'] ) : 0.0;

            if ( empty( $sub_name ) || empty( $sub_code ) ) {
                add_settings_error( 'ashoka_subjects', 'missing', __( 'Subject name and code are required.', 'ashoka-exam-section' ), 'error' );
                return;
            }

            $sno = isset( $_POST['sno'] ) ? absint( $_POST['sno'] ) : 0;
            $data    = array( 'sub_name' => $sub_name, 'sub_code' => $sub_code, 'credits' => $credits );
            $formats = array( '%s', '%s', '%f' );

            if ( $sno ) {
                $wpdb->update( $table, $data, array( 'sno' => $sno ), $formats, array( '%d' ) );
                add_settings_error( 'ashoka_subjects', 'updated', __( 'Subject updated.', 'ashoka-exam-section' ), 'updated' );
            } else {
                $exists = $wpdb->get_var( $wpdb->prepare( "SELECT sno FROM $table WHERE sub_code = %s", $sub_code ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                if ( $exists ) {
                    add_settings_error( 'ashoka_subjects', 'duplicate', __( 'A subject with this code already exists.', 'ashoka-exam-section' ), 'error' );
                    return;
                }
                $wpdb->insert( $table, $data, $formats );
                add_settings_error( 'ashoka_subjects', 'added', __( 'Subject added.', 'ashoka-exam-section' ), 'updated' );
            }
        }

        if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['sno'] ) ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Permission denied.', 'ashoka-exam-section' ) );
            }
            check_admin_referer( 'ashoka_subject_delete_' . absint( $_GET['sno'] ) );
            $wpdb->delete( $table, array( 'sno' => absint( $_GET['sno'] ) ), array( '%d' ) );
            wp_safe_redirect( admin_url( 'admin.php?page=ashoka-subjects' ) );
            exit;
        }

        if ( isset( $_POST['ashoka_subject_import'] ) ) {
            check_admin_referer( 'ashoka_subject_import' );
            if ( ! current_user_can( 'ashoka_import_data' ) ) {
                wp_die( esc_html__( 'Permission denied.', 'ashoka-exam-section' ) );
            }
            self::import_csv();
        }

        if ( isset( $_GET['action'] ) && 'export' === $_GET['action'] ) {
            if ( ! current_user_can( 'ashoka_export_data' ) ) {
                wp_die( esc_html__( 'Permission denied.', 'ashoka-exam-section' ) );
            }
            check_admin_referer( 'ashoka_subject_export' );
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
            $like    = '%' . $wpdb->esc_like( $search ) . '%';
            $total   = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE sub_name LIKE %s OR sub_code LIKE %s", $like, $like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE sub_name LIKE %s OR sub_code LIKE %s ORDER BY sno ASC LIMIT %d OFFSET %d", $like, $like, $per_page, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        } else {
            $total   = $wpdb->get_var( "SELECT COUNT(*) FROM $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY sno ASC LIMIT %d OFFSET %d", $per_page, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        $total_pages = ceil( $total / $per_page );
        settings_errors( 'ashoka_subjects' );
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Subjects', 'ashoka-exam-section' ); ?></h1>
            <?php if ( current_user_can( 'ashoka_manage_subjects' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ashoka-subjects&action=add' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'ashoka-exam-section' ); ?></a>
            <?php endif; ?>

            <form method="get" style="float:right;margin-top:8px;">
                <input type="hidden" name="page" value="ashoka-subjects">
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search subjects…', 'ashoka-exam-section' ); ?>">
                <button type="submit" class="button"><?php esc_html_e( 'Search', 'ashoka-exam-section' ); ?></button>
            </form>
            <br class="clear">

            <?php if ( current_user_can( 'ashoka_export_data' ) ) : ?>
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ashoka-subjects&action=export' ), 'ashoka_subject_export' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Export CSV', 'ashoka-exam-section' ); ?></a>
            <?php endif; ?>

            <?php if ( current_user_can( 'ashoka_import_data' ) ) : ?>
                <button type="button" class="button button-secondary" onclick="document.getElementById('ashoka-subject-import-form').style.display='block'"><?php esc_html_e( 'Import CSV', 'ashoka-exam-section' ); ?></button>
                <div id="ashoka-subject-import-form" style="display:none;margin-top:10px;">
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'ashoka_subject_import' ); ?>
                        <input type="file" name="import_file" accept=".csv" required>
                        <button type="submit" name="ashoka_subject_import" class="button button-primary"><?php esc_html_e( 'Upload & Import', 'ashoka-exam-section' ); ?></button>
                    </form>
                </div>
            <?php endif; ?>

            <table class="wp-list-table widefat fixed striped ashoka-table" style="margin-top:15px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'SNO', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Subject Name / Lab Name', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Subject Code', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Credits', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'ashoka-exam-section' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $results ) : ?>
                        <?php foreach ( $results as $row ) : ?>
                            <tr>
                                <td><?php echo esc_html( $row->sno ); ?></td>
                                <td><?php echo esc_html( $row->sub_name ); ?></td>
                                <td><?php echo esc_html( $row->sub_code ); ?></td>
                                <td><?php echo esc_html( $row->credits ); ?></td>
                                <td>
                                    <?php if ( current_user_can( 'ashoka_manage_subjects' ) ) : ?>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ashoka-subjects&action=edit&sno=' . $row->sno ) ); ?>"><?php esc_html_e( 'Edit', 'ashoka-exam-section' ); ?></a>
                                    <?php endif; ?>
                                    <?php if ( current_user_can( 'manage_options' ) ) : ?>
                                        &nbsp;|&nbsp;
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ashoka-subjects&action=delete&sno=' . $row->sno ), 'ashoka_subject_delete_' . $row->sno ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this subject?', 'ashoka-exam-section' ); ?>')" style="color:red;"><?php esc_html_e( 'Delete', 'ashoka-exam-section' ); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="5"><?php esc_html_e( 'No subjects found.', 'ashoka-exam-section' ); ?></td></tr>
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
        settings_errors( 'ashoka_subjects' );
        ?>
        <div class="wrap">
            <h1><?php echo 'edit' === $action ? esc_html__( 'Edit Subject', 'ashoka-exam-section' ) : esc_html__( 'Add Subject', 'ashoka-exam-section' ); ?></h1>
            <form method="post">
                <?php wp_nonce_field( 'ashoka_subject_save' ); ?>
                <?php if ( $sno ) : ?>
                    <input type="hidden" name="sno" value="<?php echo esc_attr( $sno ); ?>">
                <?php endif; ?>
                <table class="form-table">
                    <tr><th><label for="sub_name"><?php esc_html_e( 'Subject / Lab Name *', 'ashoka-exam-section' ); ?></label></th>
                        <td><input type="text" id="sub_name" name="sub_name" class="regular-text" value="<?php echo $row ? esc_attr( $row->sub_name ) : ''; ?>" required></td></tr>
                    <tr><th><label for="sub_code"><?php esc_html_e( 'Subject Code *', 'ashoka-exam-section' ); ?></label></th>
                        <td><input type="text" id="sub_code" name="sub_code" class="regular-text" value="<?php echo $row ? esc_attr( $row->sub_code ) : ''; ?>" required></td></tr>
                    <tr><th><label for="credits"><?php esc_html_e( 'Credits', 'ashoka-exam-section' ); ?></label></th>
                        <td><input type="number" id="credits" name="credits" step="0.01" min="0" class="small-text" value="<?php echo $row ? esc_attr( $row->credits ) : '0.00'; ?>"></td></tr>
                </table>
                <p class="submit">
                    <button type="submit" name="ashoka_subject_save" class="button button-primary"><?php esc_html_e( 'Save Subject', 'ashoka-exam-section' ); ?></button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ashoka-subjects' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'ashoka-exam-section' ); ?></a>
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
        header( 'Content-Disposition: attachment; filename=subjects-' . gmdate( 'Y-m-d' ) . '.csv' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'SNO', 'Subject Name', 'Subject Code', 'Credits' ) );
        foreach ( $results as $row ) {
            fputcsv( $out, array( $row['sno'], $row['sub_name'], $row['sub_code'], $row['credits'] ) );
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
            add_settings_error( 'ashoka_subjects', 'no_file', __( 'Please select a CSV file.', 'ashoka-exam-section' ), 'error' );
            return;
        }

        $file = fopen( sanitize_text_field( wp_unslash( $_FILES['import_file']['tmp_name'] ) ), 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        if ( ! $file ) {
            add_settings_error( 'ashoka_subjects', 'open_fail', __( 'Cannot open CSV file.', 'ashoka-exam-section' ), 'error' );
            return;
        }

        fgetcsv( $file ); // skip header
        $count = 0;
        while ( ( $row = fgetcsv( $file ) ) !== false ) {
            if ( empty( $row[2] ) ) {
                continue;
            }
            $sub_code = sanitize_text_field( $row[2] );
            $exists   = $wpdb->get_var( $wpdb->prepare( "SELECT sno FROM $table WHERE sub_code = %s", $sub_code ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ( ! $exists ) {
                $wpdb->insert( $table, array(
                    'sub_name' => sanitize_text_field( $row[1] ?? '' ),
                    'sub_code' => $sub_code,
                    'credits'  => isset( $row[3] ) ? floatval( $row[3] ) : 0.0,
                ), array( '%s', '%s', '%f' ) );
                $count++;
            }
        }
        fclose( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        add_settings_error( 'ashoka_subjects', 'imported', sprintf( __( 'Imported %d subject(s).', 'ashoka-exam-section' ), $count ), 'updated' );
    }
}
