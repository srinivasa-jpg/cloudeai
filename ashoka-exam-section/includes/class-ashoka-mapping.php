<?php
/**
 * Faculty ↔ Subject ↔ Branch mapping.
 *
 * @package Ashoka_Exam_Section
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ashoka_Mapping {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'ashoka_faculty_subject_mapping';
    }

    private static function years() {
        return array( 'I Year', 'II Year', 'III Year', 'IV Year' );
    }

    private static function sems() {
        return array( 'I Sem', 'II Sem' );
    }

    // ----------------------------------------------------------------
    // Admin page renderer
    // ----------------------------------------------------------------
    public static function render_page() {
        if ( ! current_user_can( 'ashoka_manage_mapping' ) ) {
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

        if ( isset( $_POST['ashoka_mapping_save'] ) ) {
            check_admin_referer( 'ashoka_mapping_save' );
            if ( ! current_user_can( 'ashoka_manage_mapping' ) ) {
                wp_die( esc_html__( 'Permission denied.', 'ashoka-exam-section' ) );
            }

            $faculty_id = absint( $_POST['faculty_id'] ?? 0 );
            $sub_code   = sanitize_text_field( wp_unslash( $_POST['sub_code'] ?? '' ) );
            $branch     = sanitize_text_field( wp_unslash( $_POST['branch'] ?? '' ) );
            $year       = sanitize_text_field( wp_unslash( $_POST['year'] ?? '' ) );
            $sem        = sanitize_text_field( wp_unslash( $_POST['sem'] ?? '' ) );

            if ( ! $faculty_id || empty( $sub_code ) || empty( $branch ) || empty( $year ) || empty( $sem ) ) {
                add_settings_error( 'ashoka_mapping', 'missing', __( 'All fields are required.', 'ashoka-exam-section' ), 'error' );
                return;
            }

            $sno  = isset( $_POST['sno'] ) ? absint( $_POST['sno'] ) : 0;
            $data = array(
                'faculty_id' => $faculty_id,
                'sub_code'   => $sub_code,
                'branch'     => $branch,
                'year'       => $year,
                'sem'        => $sem,
            );
            $formats = array( '%d', '%s', '%s', '%s', '%s' );

            if ( $sno ) {
                $wpdb->update( $table, $data, array( 'sno' => $sno ), $formats, array( '%d' ) );
                add_settings_error( 'ashoka_mapping', 'updated', __( 'Mapping updated.', 'ashoka-exam-section' ), 'updated' );
            } else {
                $wpdb->insert( $table, $data, $formats );
                add_settings_error( 'ashoka_mapping', 'added', __( 'Mapping added.', 'ashoka-exam-section' ), 'updated' );
            }
        }

        if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['sno'] ) ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Permission denied.', 'ashoka-exam-section' ) );
            }
            check_admin_referer( 'ashoka_mapping_delete_' . absint( $_GET['sno'] ) );
            $wpdb->delete( $table, array( 'sno' => absint( $_GET['sno'] ) ), array( '%d' ) );
            wp_safe_redirect( admin_url( 'admin.php?page=ashoka-mapping' ) );
            exit;
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

        $total   = $wpdb->get_var( "SELECT COUNT(*) FROM $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT m.*, f.faculty_name, f.emp_code, s.sub_name
             FROM $table m
             LEFT JOIN {$wpdb->prefix}ashoka_faculty f ON f.sno = m.faculty_id
             LEFT JOIN {$wpdb->prefix}ashoka_subjects s ON s.sub_code = m.sub_code
             ORDER BY m.sno ASC LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );

        $total_pages = ceil( $total / $per_page );
        settings_errors( 'ashoka_mapping' );
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Faculty-Subject Mapping', 'ashoka-exam-section' ); ?></h1>
            <?php if ( current_user_can( 'ashoka_manage_mapping' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ashoka-mapping&action=add' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Mapping', 'ashoka-exam-section' ); ?></a>
            <?php endif; ?>

            <table class="wp-list-table widefat fixed striped ashoka-table" style="margin-top:15px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'SNO', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Faculty', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Sub Name', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Sub Code', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Branch', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Year', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Sem', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'ashoka-exam-section' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $results ) : ?>
                        <?php foreach ( $results as $row ) : ?>
                            <tr>
                                <td><?php echo esc_html( $row->sno ); ?></td>
                                <td><?php echo esc_html( $row->faculty_name . ' (' . $row->emp_code . ')' ); ?></td>
                                <td><?php echo esc_html( $row->sub_name ); ?></td>
                                <td><?php echo esc_html( $row->sub_code ); ?></td>
                                <td><?php echo esc_html( $row->branch ); ?></td>
                                <td><?php echo esc_html( $row->year ); ?></td>
                                <td><?php echo esc_html( $row->sem ); ?></td>
                                <td>
                                    <?php if ( current_user_can( 'ashoka_manage_mapping' ) ) : ?>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ashoka-mapping&action=edit&sno=' . $row->sno ) ); ?>"><?php esc_html_e( 'Edit', 'ashoka-exam-section' ); ?></a>
                                    <?php endif; ?>
                                    <?php if ( current_user_can( 'manage_options' ) ) : ?>
                                        &nbsp;|&nbsp;
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ashoka-mapping&action=delete&sno=' . $row->sno ), 'ashoka_mapping_delete_' . $row->sno ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this mapping?', 'ashoka-exam-section' ); ?>')" style="color:red;"><?php esc_html_e( 'Delete', 'ashoka-exam-section' ); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="8"><?php esc_html_e( 'No mappings found.', 'ashoka-exam-section' ); ?></td></tr>
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
        $table    = self::table();
        $sno      = isset( $_GET['sno'] ) ? absint( $_GET['sno'] ) : 0;
        $row      = $sno ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE sno = %d", $sno ) ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $faculties = Ashoka_DB::get_faculty();
        $subjects  = Ashoka_DB::get_subjects();
        $branches  = Ashoka_DB::get_branches();
        settings_errors( 'ashoka_mapping' );
        ?>
        <div class="wrap">
            <h1><?php echo 'edit' === $action ? esc_html__( 'Edit Mapping', 'ashoka-exam-section' ) : esc_html__( 'Add Mapping', 'ashoka-exam-section' ); ?></h1>
            <form method="post">
                <?php wp_nonce_field( 'ashoka_mapping_save' ); ?>
                <?php if ( $sno ) : ?>
                    <input type="hidden" name="sno" value="<?php echo esc_attr( $sno ); ?>">
                <?php endif; ?>
                <table class="form-table">
                    <tr><th><label for="faculty_id"><?php esc_html_e( 'Faculty *', 'ashoka-exam-section' ); ?></label></th>
                        <td>
                            <select id="faculty_id" name="faculty_id" required>
                                <option value=""><?php esc_html_e( '— Select Faculty —', 'ashoka-exam-section' ); ?></option>
                                <?php foreach ( $faculties as $f ) : ?>
                                    <option value="<?php echo esc_attr( $f->sno ); ?>" <?php selected( $row ? $row->faculty_id : 0, $f->sno ); ?>><?php echo esc_html( $f->faculty_name . ' (' . $f->emp_code . ')' ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td></tr>
                    <tr><th><label for="sub_code"><?php esc_html_e( 'Subject *', 'ashoka-exam-section' ); ?></label></th>
                        <td>
                            <select id="sub_code" name="sub_code" required>
                                <option value=""><?php esc_html_e( '— Select Subject —', 'ashoka-exam-section' ); ?></option>
                                <?php foreach ( $subjects as $s ) : ?>
                                    <option value="<?php echo esc_attr( $s->sub_code ); ?>" <?php selected( $row ? $row->sub_code : '', $s->sub_code ); ?>><?php echo esc_html( $s->sub_name . ' (' . $s->sub_code . ')' ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td></tr>
                    <tr><th><label for="branch"><?php esc_html_e( 'Branch *', 'ashoka-exam-section' ); ?></label></th>
                        <td>
                            <select id="branch" name="branch" required>
                                <option value=""><?php esc_html_e( '— Select Branch —', 'ashoka-exam-section' ); ?></option>
                                <?php foreach ( $branches as $b ) : ?>
                                    <option value="<?php echo esc_attr( $b->branch_name ); ?>" <?php selected( $row ? $row->branch : '', $b->branch_name ); ?>><?php echo esc_html( $b->branch_name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td></tr>
                    <tr><th><label for="year"><?php esc_html_e( 'Year *', 'ashoka-exam-section' ); ?></label></th>
                        <td>
                            <select id="year" name="year" required>
                                <option value=""><?php esc_html_e( '— Select Year —', 'ashoka-exam-section' ); ?></option>
                                <?php foreach ( self::years() as $y ) : ?>
                                    <option value="<?php echo esc_attr( $y ); ?>" <?php selected( $row ? $row->year : '', $y ); ?>><?php echo esc_html( $y ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td></tr>
                    <tr><th><label for="sem"><?php esc_html_e( 'Sem *', 'ashoka-exam-section' ); ?></label></th>
                        <td>
                            <select id="sem" name="sem" required>
                                <option value=""><?php esc_html_e( '— Select Sem —', 'ashoka-exam-section' ); ?></option>
                                <?php foreach ( self::sems() as $s ) : ?>
                                    <option value="<?php echo esc_attr( $s ); ?>" <?php selected( $row ? $row->sem : '', $s ); ?>><?php echo esc_html( $s ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td></tr>
                </table>
                <p class="submit">
                    <button type="submit" name="ashoka_mapping_save" class="button button-primary"><?php esc_html_e( 'Save Mapping', 'ashoka-exam-section' ); ?></button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ashoka-mapping' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'ashoka-exam-section' ); ?></a>
                </p>
            </form>
        </div>
        <?php
    }
}
