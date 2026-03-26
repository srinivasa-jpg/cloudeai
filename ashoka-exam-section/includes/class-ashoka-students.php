<?php
/**
 * Student CRUD, import, and export.
 *
 * @package Ashoka_Exam_Section
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ashoka_Students {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'ashoka_students';
    }

    private static function years() {
        return array( 'I Year', 'II Year', 'III Year', 'IV Year' );
    }

    private static function sems() {
        return array( 'I Sem', 'II Sem' );
    }

    private static function sections() {
        return array( 'A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P' );
    }

    // ----------------------------------------------------------------
    // Admin page renderer
    // ----------------------------------------------------------------
    public static function render_page() {
        $cap = current_user_can( 'manage_options' ) || current_user_can( 'ashoka_manage_students' ) || current_user_can( 'ashoka_view_students' );
        if ( ! $cap ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'ashoka-exam-section' ) );
        }

        self::handle_actions();

        $action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

        if ( ( 'add' === $action || 'edit' === $action ) && ( current_user_can( 'ashoka_manage_students' ) || current_user_can( 'manage_options' ) ) ) {
            self::render_form( $action );
        } else {
            self::render_list();
        }
    }

    // ----------------------------------------------------------------
    // Handle form submissions & URL actions
    // ----------------------------------------------------------------
    private static function handle_actions() {
        global $wpdb;
        $table = self::table();

        // Save student (add/edit).
        if ( isset( $_POST['ashoka_student_save'] ) ) {
            check_admin_referer( 'ashoka_student_save' );
            if ( ! current_user_can( 'ashoka_manage_students' ) && ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Permission denied.', 'ashoka-exam-section' ) );
            }

            $data = array(
                'htno'               => sanitize_text_field( wp_unslash( $_POST['htno'] ?? '' ) ),
                'student_name'       => sanitize_text_field( wp_unslash( $_POST['student_name'] ?? '' ) ),
                'branch'             => sanitize_text_field( wp_unslash( $_POST['branch'] ?? '' ) ),
                'year'               => sanitize_text_field( wp_unslash( $_POST['year'] ?? '' ) ),
                'sem'                => sanitize_text_field( wp_unslash( $_POST['sem'] ?? '' ) ),
                'section'            => sanitize_text_field( wp_unslash( $_POST['section'] ?? '' ) ),
                'admn_no'            => sanitize_text_field( wp_unslash( $_POST['admn_no'] ?? '' ) ),
                'caste_category'     => sanitize_text_field( wp_unslash( $_POST['caste_category'] ?? '' ) ),
                'admn_dt'            => sanitize_text_field( wp_unslash( $_POST['admn_dt'] ?? '' ) ) ?: null,
                'year_of_completion' => sanitize_text_field( wp_unslash( $_POST['year_of_completion'] ?? '' ) ),
                'dob'                => sanitize_text_field( wp_unslash( $_POST['dob'] ?? '' ) ) ?: null,
                'gender'             => isset( $_POST['gender'] ) ? absint( $_POST['gender'] ) : 0,
                'father_name'        => sanitize_text_field( wp_unslash( $_POST['father_name'] ?? '' ) ),
                'mother_name'        => sanitize_text_field( wp_unslash( $_POST['mother_name'] ?? '' ) ),
                'parent_mobile'      => sanitize_text_field( wp_unslash( $_POST['parent_mobile'] ?? '' ) ),
                'student_mobile'     => sanitize_text_field( wp_unslash( $_POST['student_mobile'] ?? '' ) ),
                'email'              => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
                'dt_of_leaving'      => sanitize_text_field( wp_unslash( $_POST['dt_of_leaving'] ?? '' ) ) ?: null,
                'discon_date'        => sanitize_text_field( wp_unslash( $_POST['discon_date'] ?? '' ) ) ?: null,
                'roll_section_no'    => sanitize_text_field( wp_unslash( $_POST['roll_section_no'] ?? '' ) ),
            );

            if ( empty( $data['htno'] ) || empty( $data['student_name'] ) ) {
                add_settings_error( 'ashoka_students', 'missing', __( 'HTNo and Student Name are required.', 'ashoka-exam-section' ), 'error' );
                return;
            }

            $formats = array( '%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s','%s','%s' );

            $sno = isset( $_POST['sno'] ) ? absint( $_POST['sno'] ) : 0;
            if ( $sno ) {
                $wpdb->update( $table, $data, array( 'sno' => $sno ), $formats, array( '%d' ) );
                add_settings_error( 'ashoka_students', 'updated', __( 'Student updated.', 'ashoka-exam-section' ), 'updated' );
            } else {
                // Check unique HTNo.
                $exists = $wpdb->get_var( $wpdb->prepare( "SELECT sno FROM $table WHERE htno = %s", $data['htno'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                if ( $exists ) {
                    add_settings_error( 'ashoka_students', 'duplicate', __( 'A student with this HTNo already exists.', 'ashoka-exam-section' ), 'error' );
                    return;
                }
                $wpdb->insert( $table, $data, $formats );
                add_settings_error( 'ashoka_students', 'added', __( 'Student added.', 'ashoka-exam-section' ), 'updated' );
            }
            wp_safe_redirect( admin_url( 'admin.php?page=ashoka-students' ) );
            exit;
        }

        // Delete.
        if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['sno'] ) ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Permission denied.', 'ashoka-exam-section' ) );
            }
            check_admin_referer( 'ashoka_student_delete_' . absint( $_GET['sno'] ) );
            $wpdb->delete( $table, array( 'sno' => absint( $_GET['sno'] ) ), array( '%d' ) );
            wp_safe_redirect( admin_url( 'admin.php?page=ashoka-students' ) );
            exit;
        }

        // Import CSV.
        if ( isset( $_POST['ashoka_student_import'] ) ) {
            check_admin_referer( 'ashoka_student_import' );
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
            check_admin_referer( 'ashoka_student_export' );
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

        $where = '';
        $args  = array();
        if ( $search ) {
            $like   = '%' . $wpdb->esc_like( $search ) . '%';
            $where  = 'WHERE htno LIKE %s OR student_name LIKE %s';
            $args   = array( $like, $like );
        }

        if ( $where ) {
            $total   = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table $where", ...$args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table $where ORDER BY sno ASC LIMIT %d OFFSET %d", ...array_merge( $args, array( $per_page, $offset ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        } else {
            $total   = $wpdb->get_var( "SELECT COUNT(*) FROM $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY sno ASC LIMIT %d OFFSET %d", $per_page, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        $total_pages = ceil( $total / $per_page );
        settings_errors( 'ashoka_students' );
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Students', 'ashoka-exam-section' ); ?></h1>
            <?php if ( current_user_can( 'ashoka_manage_students' ) || current_user_can( 'manage_options' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ashoka-students&action=add' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'ashoka-exam-section' ); ?></a>
            <?php endif; ?>

            <form method="get" style="float:right;margin-top:8px;">
                <input type="hidden" name="page" value="ashoka-students">
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by name or HTNo…', 'ashoka-exam-section' ); ?>">
                <button type="submit" class="button"><?php esc_html_e( 'Search', 'ashoka-exam-section' ); ?></button>
            </form>
            <br class="clear">

            <?php if ( current_user_can( 'ashoka_export_data' ) ) : ?>
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ashoka-students&action=export' ), 'ashoka_student_export' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Export CSV', 'ashoka-exam-section' ); ?></a>
            <?php endif; ?>

            <?php if ( current_user_can( 'ashoka_import_data' ) ) : ?>
                <button type="button" class="button button-secondary" onclick="document.getElementById('ashoka-student-import-form').style.display='block'"><?php esc_html_e( 'Import CSV', 'ashoka-exam-section' ); ?></button>
                <div id="ashoka-student-import-form" style="display:none;margin-top:10px;">
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'ashoka_student_import' ); ?>
                        <input type="file" name="import_file" accept=".csv" required>
                        <button type="submit" name="ashoka_student_import" class="button button-primary"><?php esc_html_e( 'Upload & Import', 'ashoka-exam-section' ); ?></button>
                    </form>
                </div>
            <?php endif; ?>

            <table class="wp-list-table widefat fixed striped ashoka-table" style="margin-top:15px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'SNO', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'HTNo', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Student Name', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Branch', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Year', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Sem', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Section', 'ashoka-exam-section' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'ashoka-exam-section' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $results ) : ?>
                        <?php foreach ( $results as $row ) : ?>
                            <tr>
                                <td><?php echo esc_html( $row->sno ); ?></td>
                                <td><?php echo esc_html( $row->htno ); ?></td>
                                <td><?php echo esc_html( $row->student_name ); ?></td>
                                <td><?php echo esc_html( $row->branch ); ?></td>
                                <td><?php echo esc_html( $row->year ); ?></td>
                                <td><?php echo esc_html( $row->sem ); ?></td>
                                <td><?php echo esc_html( $row->section ); ?></td>
                                <td>
                                    <?php if ( current_user_can( 'ashoka_manage_students' ) || current_user_can( 'manage_options' ) ) : ?>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ashoka-students&action=edit&sno=' . $row->sno ) ); ?>"><?php esc_html_e( 'Edit', 'ashoka-exam-section' ); ?></a>
                                    <?php endif; ?>
                                    <?php if ( current_user_can( 'manage_options' ) ) : ?>
                                        &nbsp;|&nbsp;
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ashoka-students&action=delete&sno=' . $row->sno ), 'ashoka_student_delete_' . $row->sno ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this student?', 'ashoka-exam-section' ); ?>')" style="color:red;"><?php esc_html_e( 'Delete', 'ashoka-exam-section' ); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="8"><?php esc_html_e( 'No students found.', 'ashoka-exam-section' ); ?></td></tr>
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
        $table  = self::table();
        $sno    = isset( $_GET['sno'] ) ? absint( $_GET['sno'] ) : 0;
        $row    = $sno ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE sno = %d", $sno ) ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $branches = Ashoka_DB::get_branches();
        settings_errors( 'ashoka_students' );

        $v = function( $field ) use ( $row ) {
            return $row ? esc_attr( $row->$field ) : '';
        };
        ?>
        <div class="wrap">
            <h1><?php echo 'edit' === $action ? esc_html__( 'Edit Student', 'ashoka-exam-section' ) : esc_html__( 'Add Student', 'ashoka-exam-section' ); ?></h1>
            <form method="post">
                <?php wp_nonce_field( 'ashoka_student_save' ); ?>
                <?php if ( $sno ) : ?>
                    <input type="hidden" name="sno" value="<?php echo esc_attr( $sno ); ?>">
                <?php endif; ?>
                <table class="form-table">
                    <tr><th><label for="htno"><?php esc_html_e( 'HTNo *', 'ashoka-exam-section' ); ?></label></th>
                        <td><input type="text" id="htno" name="htno" class="regular-text" value="<?php echo $v('htno'); ?>" required <?php echo $sno ? 'readonly' : ''; ?>></td></tr>
                    <tr><th><label for="student_name"><?php esc_html_e( 'Student Name *', 'ashoka-exam-section' ); ?></label></th>
                        <td><input type="text" id="student_name" name="student_name" class="regular-text" value="<?php echo $v('student_name'); ?>" required></td></tr>
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
                    <tr><th><label for="section"><?php esc_html_e( 'Section', 'ashoka-exam-section' ); ?></label></th>
                        <td>
                            <select id="section" name="section">
                                <option value=""><?php esc_html_e( '— Select Section —', 'ashoka-exam-section' ); ?></option>
                                <?php foreach ( self::sections() as $sec ) : ?>
                                    <option value="<?php echo esc_attr( $sec ); ?>" <?php selected( $row ? $row->section : '', $sec ); ?>><?php echo esc_html( $sec ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td></tr>
                    <tr><th><label for="admn_no"><?php esc_html_e( 'Admission No', 'ashoka-exam-section' ); ?></label></th>
                        <td><input type="text" id="admn_no" name="admn_no" class="regular-text" value="<?php echo $v('admn_no'); ?>"></td></tr>
                    <tr><th><label for="caste_category"><?php esc_html_e( 'Caste Category', 'ashoka-exam-section' ); ?></label></th>
                        <td><input type="text" id="caste_category" name="caste_category" class="regular-text" value="<?php echo $v('caste_category'); ?>"></td></tr>
                    <tr><th><label for="admn_dt"><?php esc_html_e( 'Admission Date', 'ashoka-exam-section' ); ?></label></th>
                        <td><input type="date" id="admn_dt" name="admn_dt" value="<?php echo $v('admn_dt'); ?>"></td></tr>
                    <tr><th><label for="year_of_completion"><?php esc_html_e( 'Year of Completion', 'ashoka-exam-section' ); ?></label></th>
                        <td><input type="text" id="year_of_completion" name="year_of_completion" class="regular-text" value="<?php echo $v('year_of_completion'); ?>"></td></tr>
                    <tr><th><label for="dob"><?php esc_html_e( 'Date of Birth', 'ashoka-exam-section' ); ?></label></th>
                        <td><input type="date" id="dob" name="dob" value="<?php echo $v('dob'); ?>"></td></tr>
                    <tr><th><label for="gender"><?php esc_html_e( 'Gender', 'ashoka-exam-section' ); ?></label></th>
                        <td>
                            <select id="gender" name="gender">
                                <option value="0" <?php selected( $row ? $row->gender : 0, 0 ); ?>><?php esc_html_e( 'Male', 'ashoka-exam-section' ); ?></option>
                                <option value="1" <?php selected( $row ? $row->gender : 0, 1 ); ?>><?php esc_html_e( 'Female', 'ashoka-exam-section' ); ?></option>
                            </select>
                        </td></tr>
                    <tr><th><label for="father_name"><?php esc_html_e( 'Father Name', 'ashoka-exam-section' ); ?></label></th>
                        <td><input type="text" id="father_name" name="father_name" class="regular-text" value="<?php echo $v('father_name'); ?>"></td></tr>
                    <tr><th><label for="mother_name"><?php esc_html_e( 'Mother Name', 'ashoka-exam-section' ); ?></label></th>
                        <td><input type="text" id="mother_name" name="mother_name" class="regular-text" value="<?php echo $v('mother_name'); ?>"></td></tr>
                    <tr><th><label for="parent_mobile"><?php esc_html_e( 'Parent Mobile', 'ashoka-exam-section' ); ?></label></th>
                        <td><input type="text" id="parent_mobile" name="parent_mobile" class="regular-text" value="<?php echo $v('parent_mobile'); ?>"></td></tr>
                    <tr><th><label for="student_mobile"><?php esc_html_e( 'Student Mobile', 'ashoka-exam-section' ); ?></label></th>
                        <td><input type="text" id="student_mobile" name="student_mobile" class="regular-text" value="<?php echo $v('student_mobile'); ?>"></td></tr>
                    <tr><th><label for="email"><?php esc_html_e( 'Email', 'ashoka-exam-section' ); ?></label></th>
                        <td><input type="email" id="email" name="email" class="regular-text" value="<?php echo $v('email'); ?>"></td></tr>
                    <tr><th><label for="dt_of_leaving"><?php esc_html_e( 'Date of Leaving', 'ashoka-exam-section' ); ?></label></th>
                        <td><input type="date" id="dt_of_leaving" name="dt_of_leaving" value="<?php echo $v('dt_of_leaving'); ?>"></td></tr>
                    <tr><th><label for="discon_date"><?php esc_html_e( 'Discontinuation Date', 'ashoka-exam-section' ); ?></label></th>
                        <td><input type="date" id="discon_date" name="discon_date" value="<?php echo $v('discon_date'); ?>"></td></tr>
                    <tr><th><label for="roll_section_no"><?php esc_html_e( 'Roll/Section No', 'ashoka-exam-section' ); ?></label></th>
                        <td><input type="text" id="roll_section_no" name="roll_section_no" class="regular-text" value="<?php echo $v('roll_section_no'); ?>"></td></tr>
                </table>
                <p class="submit">
                    <button type="submit" name="ashoka_student_save" class="button button-primary"><?php esc_html_e( 'Save Student', 'ashoka-exam-section' ); ?></button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ashoka-students' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'ashoka-exam-section' ); ?></a>
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
        header( 'Content-Disposition: attachment; filename=students-' . gmdate( 'Y-m-d' ) . '.csv' );

        $out     = fopen( 'php://output', 'w' );
        $headers = array( 'SNO','HTNo','Student Name','Branch','Year','Sem','Section','Admn No','Caste Category','Admn Date','Year of Completion','DOB','Gender','Father Name','Mother Name','Parent Mobile','Student Mobile','Email','Dt of Leaving','Discon Date','Roll/Section No' );
        fputcsv( $out, $headers );
        foreach ( $results as $row ) {
            fputcsv( $out, array(
                $row['sno'], $row['htno'], $row['student_name'], $row['branch'], $row['year'], $row['sem'],
                $row['section'], $row['admn_no'], $row['caste_category'], $row['admn_dt'], $row['year_of_completion'],
                $row['dob'], ( 0 === (int) $row['gender'] ? 'Male' : 'Female' ), $row['father_name'], $row['mother_name'],
                $row['parent_mobile'], $row['student_mobile'], $row['email'], $row['dt_of_leaving'],
                $row['discon_date'], $row['roll_section_no'],
            ) );
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
            add_settings_error( 'ashoka_students', 'no_file', __( 'Please select a CSV file.', 'ashoka-exam-section' ), 'error' );
            return;
        }

        $file = fopen( sanitize_text_field( wp_unslash( $_FILES['import_file']['tmp_name'] ) ), 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        if ( ! $file ) {
            add_settings_error( 'ashoka_students', 'open_fail', __( 'Cannot open CSV file.', 'ashoka-exam-section' ), 'error' );
            return;
        }

        $header = fgetcsv( $file ); // skip header
        $count  = 0;
        $errors = 0;
        while ( ( $row = fgetcsv( $file ) ) !== false ) {
            if ( empty( $row[1] ) || empty( $row[2] ) ) {
                continue;
            }
            $htno = sanitize_text_field( $row[1] );
            // Skip if already exists.
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT sno FROM $table WHERE htno = %s", $htno ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ( $exists ) {
                $errors++;
                continue;
            }
            $wpdb->insert( $table, array(
                'htno'               => $htno,
                'student_name'       => sanitize_text_field( $row[2] ?? '' ),
                'branch'             => sanitize_text_field( $row[3] ?? '' ),
                'year'               => sanitize_text_field( $row[4] ?? '' ),
                'sem'                => sanitize_text_field( $row[5] ?? '' ),
                'section'            => sanitize_text_field( $row[6] ?? '' ),
                'admn_no'            => sanitize_text_field( $row[7] ?? '' ),
                'caste_category'     => sanitize_text_field( $row[8] ?? '' ),
                'admn_dt'            => sanitize_text_field( $row[9] ?? '' ) ?: null,
                'year_of_completion' => sanitize_text_field( $row[10] ?? '' ),
                'dob'                => sanitize_text_field( $row[11] ?? '' ) ?: null,
                'gender'             => isset( $row[12] ) && 'Female' === $row[12] ? 1 : 0,
                'father_name'        => sanitize_text_field( $row[13] ?? '' ),
                'mother_name'        => sanitize_text_field( $row[14] ?? '' ),
                'parent_mobile'      => sanitize_text_field( $row[15] ?? '' ),
                'student_mobile'     => sanitize_text_field( $row[16] ?? '' ),
                'email'              => sanitize_email( $row[17] ?? '' ),
                'dt_of_leaving'      => sanitize_text_field( $row[18] ?? '' ) ?: null,
                'discon_date'        => sanitize_text_field( $row[19] ?? '' ) ?: null,
                'roll_section_no'    => sanitize_text_field( $row[20] ?? '' ),
            ) );
            $count++;
        }
        fclose( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

        $msg = sprintf( __( 'Imported %d student(s).', 'ashoka-exam-section' ), $count );
        if ( $errors ) {
            $msg .= ' ' . sprintf( __( '%d duplicate HTNo(s) skipped.', 'ashoka-exam-section' ), $errors );
        }
        add_settings_error( 'ashoka_students', 'imported', $msg, 'updated' );
    }
}
