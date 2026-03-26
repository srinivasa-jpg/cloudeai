<?php
/**
 * External marks management.
 *
 * @package Ashoka_Exam_Section
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ashoka_External_Marks {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'ashoka_external_marks';
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
        if ( ! current_user_can( 'ashoka_manage_marks' ) ) {
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

        if ( isset( $_POST['ashoka_ext_marks_save'] ) ) {
            check_admin_referer( 'ashoka_ext_marks_save' );
            if ( ! current_user_can( 'ashoka_manage_marks' ) ) {
                wp_die( esc_html__( 'Permission denied.', 'ashoka-exam-section' ) );
            }

            $htno          = sanitize_text_field( wp_unslash( $_POST['student_htno'] ?? '' ) );
            $sub_code      = sanitize_text_field( wp_unslash( $_POST['sub_code'] ?? '' ) );
            $branch        = sanitize_text_field( wp_unslash( $_POST['branch'] ?? '' ) );
            $year          = sanitize_text_field( wp_unslash( $_POST['year'] ?? '' ) );
            $sem           = sanitize_text_field( wp_unslash( $_POST['sem'] ?? '' ) );
            $ext_marks     = isset( $_POST['external_marks'] ) ? floatval( $_POST['external_marks'] ) : 0;
            $exam_type     = sanitize_text_field( wp_unslash( $_POST['exam_type'] ?? 'subject' ) );

            // Validate exam type.
            if ( ! in_array( $exam_type, array( 'subject', 'lab' ), true ) ) {
                $exam_type = 'subject';
            }

            // Validate marks range.
            if ( $ext_marks < 0 || $ext_marks > 70 ) {
                add_settings_error( 'ashoka_ext_marks', 'range', __( 'External marks must be between 0 and 70.', 'ashoka-exam-section' ), 'error' );
                return;
            }

            if ( empty( $htno ) || empty( $sub_code ) ) {
                add_settings_error( 'ashoka_ext_marks', 'missing', __( 'HTNo and Subject Code are required.', 'ashoka-exam-section' ), 'error' );
                return;
            }

            $sno  = isset( $_POST['sno'] ) ? absint( $_POST['sno'] ) : 0;
            $data = array(
                'student_htno'   => $htno,
                'sub_code'       => $sub_code,
                'branch'         => $branch,
                'year'           => $year,
                'sem'            => $sem,
                'external_marks' => $ext_marks,
                'exam_type'      => $exam_type,
            );
            $formats = array( '%s', '%s', '%s', '%s', '%s', '%f', '%s' );

            if ( $sno ) {
                $wpdb->update( $table, $data, array( 'sno' => $sno ), $formats, array( '%d' ) );
                add_settings_error( 'ashoka_ext_marks', 'updated', __( 'External marks updated.', 'ashoka-exam-section' ), 'updated' );
            } else {
                $wpdb->insert( $table, $data, $formats );
                add_settings_error( 'ashoka_ext_marks', 'added', __( 'External marks added.', 'ashoka-exam-section' ), 'updated' );
            }
        }

        if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['sno'] ) ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Permission denied.', 'ashoka-exam-section' ) );
            }
            check_admin_referer( 'ashoka_ext_marks_delete_' . absint( $_GET['sno'] ) );
            $wpdb->delete( $table, array( 'sno' => absint( $_GET['sno'] ) ), array( '%d' ) );
            wp_safe_redirect( admin_url( 'admin.php?page=ashoka-external-marks' ) );
            exit;
        }
    }

    // ----------------------------------------------------------------
    // List view
    // ----------------------------------------------------------------
    private static function render_list() {
        global $wpdb;
        $table        = self::table();
        $students_tbl = $wpdb->prefix . 'ashoka_students';
        $subjects_tbl = $wpdb->prefix . 'ashoka_subjects';
        $per_page     = 20;
        $paged        = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $offset       = ( $paged - 1 ) * $per_page;
        $search       = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

        if ( $search ) {
            $like    = '%' . $wpdb->esc_like( $search ) . '%';
            $total   = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table m LEFT JOIN $students_tbl st ON st.htno = m.student_htno WHERE m.student_htno LIKE %s OR st.student_name LIKE %s", $like, $like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $results = $wpdb->get_results( $wpdb->prepare( "SELECT m.*, st.student_name, s.sub_name FROM $table m LEFT JOIN $students_tbl st ON st.htno = m.student_htno LEFT JOIN $subjects_tbl s ON s.sub_code = m.sub_code WHERE m.student_htno LIKE %s OR st.student_name LIKE %s ORDER BY m.sno ASC LIMIT %d OFFSET %d", $like, $like, $per_page, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        } else {
            $total   = $wpdb->get_var( "SELECT COUNT(*) FROM $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results( $wpdb->prepare( "SELECT m.*, st.student_name, s.sub_name FROM $table m LEFT JOIN $students_tbl st ON st.htno = m.student_htno LEFT JOIN $subjects_tbl s ON s.sub_code = m.sub_code ORDER BY m.sno ASC LIMIT %d OFFSET %d", $per_page, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        $total_pages = ceil( $total / $per_page );
        settings_errors( 'ashoka_ext_marks' );
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'External Marks', 'ashoka-exam-section' ); ?></h1>
            <?php if ( current_user_can( 'ashoka_manage_marks' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ashoka-external-marks&action=add' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'ashoka-exam-section' ); ?></a>
            <?php endif; ?>

            <form method="get" style="float:right;margin-top:8px;">
                <input type="hidden" name="page" value="ashoka-external-marks">
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by HTNo or name…', 'ashoka-exam-section' ); ?>">
                <button type="submit" class="button"><?php esc_html_e( 'Search', 'ashoka-exam-section' ); ?></button>
            </form>
            <br class="clear">

            <table class="wp-list-table widefat fixed striped ashoka-table" style="margin-top:15px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'SNO', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Student', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'HTNo', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Sub Name', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Sub Code', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Branch', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Year', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Sem', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'External Marks', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Exam Type', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'ashoka-exam-section' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $results ) : ?>
                        <?php foreach ( $results as $row ) : ?>
                            <tr>
                                <td><?php echo esc_html( $row->sno ); ?></td>
                                <td><?php echo esc_html( $row->student_name ); ?></td>
                                <td><?php echo esc_html( $row->student_htno ); ?></td>
                                <td><?php echo esc_html( $row->sub_name ); ?></td>
                                <td><?php echo esc_html( $row->sub_code ); ?></td>
                                <td><?php echo esc_html( $row->branch ); ?></td>
                                <td><?php echo esc_html( $row->year ); ?></td>
                                <td><?php echo esc_html( $row->sem ); ?></td>
                                <td><?php echo esc_html( $row->external_marks ); ?></td>
                                <td><?php echo esc_html( ucfirst( $row->exam_type ) ); ?></td>
                                <td>
                                    <?php if ( current_user_can( 'ashoka_manage_marks' ) ) : ?>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ashoka-external-marks&action=edit&sno=' . $row->sno ) ); ?>"><?php esc_html_e( 'Edit', 'ashoka-exam-section' ); ?></a>
                                    <?php endif; ?>
                                    <?php if ( current_user_can( 'manage_options' ) ) : ?>
                                        &nbsp;|&nbsp;
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ashoka-external-marks&action=delete&sno=' . $row->sno ), 'ashoka_ext_marks_delete_' . $row->sno ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this record?', 'ashoka-exam-section' ); ?>')" style="color:red;"><?php esc_html_e( 'Delete', 'ashoka-exam-section' ); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="11"><?php esc_html_e( 'No external marks records found.', 'ashoka-exam-section' ); ?></td></tr>
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
        $subjects = Ashoka_DB::get_subjects();
        $branches = Ashoka_DB::get_branches();
        settings_errors( 'ashoka_ext_marks' );
        ?>
        <div class="wrap">
            <h1><?php echo 'edit' === $action ? esc_html__( 'Edit External Marks', 'ashoka-exam-section' ) : esc_html__( 'Add External Marks', 'ashoka-exam-section' ); ?></h1>
            <form method="post">
                <?php wp_nonce_field( 'ashoka_ext_marks_save' ); ?>
                <?php if ( $sno ) : ?>
                    <input type="hidden" name="sno" value="<?php echo esc_attr( $sno ); ?>">
                <?php endif; ?>
                <table class="form-table">
                    <tr><th><label for="student_htno"><?php esc_html_e( 'Student HTNo *', 'ashoka-exam-section' ); ?></label></th>
                        <td><input type="text" id="student_htno" name="student_htno" class="regular-text" value="<?php echo $row ? esc_attr( $row->student_htno ) : ''; ?>" required></td></tr>
                    <tr><th><label for="sub_code"><?php esc_html_e( 'Subject *', 'ashoka-exam-section' ); ?></label></th>
                        <td>
                            <select id="sub_code" name="sub_code" required>
                                <option value=""><?php esc_html_e( '— Select Subject —', 'ashoka-exam-section' ); ?></option>
                                <?php foreach ( $subjects as $s ) : ?>
                                    <option value="<?php echo esc_attr( $s->sub_code ); ?>" <?php selected( $row ? $row->sub_code : '', $s->sub_code ); ?>><?php echo esc_html( $s->sub_name . ' (' . $s->sub_code . ')' ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td></tr>
                    <tr><th><label for="branch"><?php esc_html_e( 'Branch', 'ashoka-exam-section' ); ?></label></th>
                        <td>
                            <select id="branch" name="branch">
                                <option value=""><?php esc_html_e( '— Select Branch —', 'ashoka-exam-section' ); ?></option>
                                <?php foreach ( $branches as $b ) : ?>
                                    <option value="<?php echo esc_attr( $b->branch_name ); ?>" <?php selected( $row ? $row->branch : '', $b->branch_name ); ?>><?php echo esc_html( $b->branch_name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td></tr>
                    <tr><th><label for="year"><?php esc_html_e( 'Year', 'ashoka-exam-section' ); ?></label></th>
                        <td>
                            <select id="year" name="year">
                                <option value=""><?php esc_html_e( '— Select Year —', 'ashoka-exam-section' ); ?></option>
                                <?php foreach ( self::years() as $y ) : ?>
                                    <option value="<?php echo esc_attr( $y ); ?>" <?php selected( $row ? $row->year : '', $y ); ?>><?php echo esc_html( $y ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td></tr>
                    <tr><th><label for="sem"><?php esc_html_e( 'Sem', 'ashoka-exam-section' ); ?></label></th>
                        <td>
                            <select id="sem" name="sem">
                                <option value=""><?php esc_html_e( '— Select Sem —', 'ashoka-exam-section' ); ?></option>
                                <?php foreach ( self::sems() as $s ) : ?>
                                    <option value="<?php echo esc_attr( $s ); ?>" <?php selected( $row ? $row->sem : '', $s ); ?>><?php echo esc_html( $s ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td></tr>
                    <tr><th><label for="external_marks"><?php esc_html_e( 'External Marks (max 70)', 'ashoka-exam-section' ); ?></label></th>
                        <td><input type="number" id="external_marks" name="external_marks" step="0.01" min="0" max="70" class="small-text" value="<?php echo $row ? esc_attr( $row->external_marks ) : '0'; ?>"></td></tr>
                    <tr><th><label for="exam_type"><?php esc_html_e( 'Exam Type', 'ashoka-exam-section' ); ?></label></th>
                        <td>
                            <select id="exam_type" name="exam_type">
                                <option value="subject" <?php selected( $row ? $row->exam_type : 'subject', 'subject' ); ?>><?php esc_html_e( 'Subject', 'ashoka-exam-section' ); ?></option>
                                <option value="lab" <?php selected( $row ? $row->exam_type : 'subject', 'lab' ); ?>><?php esc_html_e( 'Lab', 'ashoka-exam-section' ); ?></option>
                            </select>
                        </td></tr>
                </table>
                <p class="submit">
                    <button type="submit" name="ashoka_ext_marks_save" class="button button-primary"><?php esc_html_e( 'Save Marks', 'ashoka-exam-section' ); ?></button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ashoka-external-marks' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'ashoka-exam-section' ); ?></a>
                </p>
            </form>
        </div>
        <?php
    }
}
