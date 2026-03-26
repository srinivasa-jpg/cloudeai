<?php
/**
 * Faculty marks entry page (visible to Faculty role).
 *
 * Workflow:
 * 1. Faculty selects Subject/Branch/Year/Sem from their assigned mappings.
 * 2. Students for that combination are loaded via AJAX.
 * 3. Faculty enters MID-I / MID-II marks; Total auto-calculates.
 * 4. Bulk save for all students at once.
 *
 * @package Ashoka_Exam_Section
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ashoka_Faculty_Marks {

    // ----------------------------------------------------------------
    // Bootstrap AJAX handlers
    // ----------------------------------------------------------------
    public static function init() {
        add_action( 'wp_ajax_ashoka_get_students_for_marks', array( __CLASS__, 'ajax_get_students_for_marks' ) );
    }

    // ----------------------------------------------------------------
    // Admin page renderer
    // ----------------------------------------------------------------
    public static function render_page() {
        if ( ! current_user_can( 'ashoka_enter_marks' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'ashoka-exam-section' ) );
        }

        self::handle_bulk_save();
        self::render_form();
    }

    // ----------------------------------------------------------------
    // Bulk save handler
    // ----------------------------------------------------------------
    private static function handle_bulk_save() {
        if ( ! isset( $_POST['ashoka_faculty_marks_save'] ) ) {
            return;
        }
        check_admin_referer( 'ashoka_faculty_marks_save' );
        if ( ! current_user_can( 'ashoka_enter_marks' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'ashoka-exam-section' ) );
        }

        global $wpdb;
        $table    = $wpdb->prefix . 'ashoka_internal_marks';
        $sub_code = sanitize_text_field( wp_unslash( $_POST['sub_code'] ?? '' ) );
        $branch   = sanitize_text_field( wp_unslash( $_POST['branch'] ?? '' ) );
        $year     = sanitize_text_field( wp_unslash( $_POST['year'] ?? '' ) );
        $sem      = sanitize_text_field( wp_unslash( $_POST['sem'] ?? '' ) );

        $htnos = isset( $_POST['htno'] ) ? (array) $_POST['htno'] : array();
        $mid1s = isset( $_POST['mid1'] ) ? (array) $_POST['mid1'] : array();
        $mid2s = isset( $_POST['mid2'] ) ? (array) $_POST['mid2'] : array();

        $count  = 0;
        $errors = array();
        foreach ( $htnos as $i => $htno ) {
            $htno = sanitize_text_field( wp_unslash( $htno ) );
            $mid1 = isset( $mid1s[ $i ] ) ? floatval( $mid1s[ $i ] ) : 0;
            $mid2 = isset( $mid2s[ $i ] ) ? floatval( $mid2s[ $i ] ) : 0;

            if ( $mid1 < 0 || $mid1 > 30 || $mid2 < 0 || $mid2 > 30 ) {
                $errors[] = sprintf( __( 'HTNo %s: marks must be 0–30.', 'ashoka-exam-section' ), esc_html( $htno ) );
                continue;
            }

            $total = Ashoka_DB::calc_internal_total( $mid1, $mid2 );

            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT sno FROM $table WHERE student_htno = %s AND sub_code = %s AND branch = %s AND year = %s AND sem = %s",
                $htno, $sub_code, $branch, $year, $sem
            ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

            $data    = array( 'mid1_marks' => $mid1, 'mid2_marks' => $mid2, 'total_marks' => $total );
            $formats = array( '%f', '%f', '%f' );

            if ( $existing ) {
                $wpdb->update( $table, $data, array( 'sno' => $existing ), $formats, array( '%d' ) );
            } else {
                $wpdb->insert( $table, array_merge( $data, array(
                    'student_htno' => $htno,
                    'sub_code'     => $sub_code,
                    'branch'       => $branch,
                    'year'         => $year,
                    'sem'          => $sem,
                ) ), array_merge( $formats, array( '%s', '%s', '%s', '%s', '%s' ) ) );
            }
            $count++;
        }

        if ( $errors ) {
            foreach ( $errors as $err ) {
                add_settings_error( 'ashoka_faculty_marks', 'error', $err, 'error' );
            }
        }
        if ( $count ) {
            add_settings_error( 'ashoka_faculty_marks', 'saved', sprintf( __( 'Marks saved for %d student(s).', 'ashoka-exam-section' ), $count ), 'updated' );
        }
    }

    // ----------------------------------------------------------------
    // Render the marks entry form
    // ----------------------------------------------------------------
    private static function render_form() {
        global $wpdb;
        $current_user = wp_get_current_user();
        $mapping_tbl  = $wpdb->prefix . 'ashoka_faculty_subject_mapping';
        $faculty_tbl  = $wpdb->prefix . 'ashoka_faculty';
        $subjects_tbl = $wpdb->prefix . 'ashoka_subjects';

        // For admin/data operator: show all mappings.
        // For faculty role: only show mappings belonging to this user's faculty record
        // (matched by WordPress user login / email to emp_code — or show all for simplicity;
        //  instructors assign their own faculty record via the Faculty page).
        $is_faculty_role = ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'ashoka_manage_marks' ) );

        if ( $is_faculty_role ) {
            // Attempt to match WP user login to emp_code.
            $faculty_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT sno FROM $faculty_tbl WHERE emp_code = %s",
                $current_user->user_login
            ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

            if ( $faculty_id ) {
                $mappings = $wpdb->get_results( $wpdb->prepare(
                    "SELECT m.*, s.sub_name FROM $mapping_tbl m LEFT JOIN $subjects_tbl s ON s.sub_code = m.sub_code WHERE m.faculty_id = %d",
                    $faculty_id
                ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            } else {
                $mappings = array();
            }
        } else {
            $mappings = $wpdb->get_results(
                "SELECT m.*, s.sub_name FROM $mapping_tbl m LEFT JOIN $subjects_tbl s ON s.sub_code = m.sub_code ORDER BY m.sno ASC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            );
        }

        // Selected filter values.
        $sel_sub_code = isset( $_POST['filter_sub_code'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_sub_code'] ) ) : ( isset( $_GET['sub_code'] ) ? sanitize_text_field( wp_unslash( $_GET['sub_code'] ) ) : '' );
        $sel_branch   = isset( $_POST['filter_branch'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_branch'] ) ) : ( isset( $_GET['branch'] ) ? sanitize_text_field( wp_unslash( $_GET['branch'] ) ) : '' );
        $sel_year     = isset( $_POST['filter_year'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_year'] ) ) : ( isset( $_GET['year'] ) ? sanitize_text_field( wp_unslash( $_GET['year'] ) ) : '' );
        $sel_sem      = isset( $_POST['filter_sem'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_sem'] ) ) : ( isset( $_GET['sem'] ) ? sanitize_text_field( wp_unslash( $_GET['sem'] ) ) : '' );

        // Load students if all filters are set.
        $students = array();
        $existing_marks = array();
        if ( $sel_sub_code && $sel_branch && $sel_year && $sel_sem ) {
            $students_tbl = $wpdb->prefix . 'ashoka_students';
            $students     = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM $students_tbl WHERE branch = %s AND year = %s AND sem = %s ORDER BY student_name ASC",
                $sel_branch, $sel_year, $sel_sem
            ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

            // Load existing marks.
            $marks_tbl = $wpdb->prefix . 'ashoka_internal_marks';
            $marks_raw = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM $marks_tbl WHERE sub_code = %s AND branch = %s AND year = %s AND sem = %s",
                $sel_sub_code, $sel_branch, $sel_year, $sel_sem
            ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

            foreach ( $marks_raw as $m ) {
                $existing_marks[ $m->student_htno ] = $m;
            }
        }

        settings_errors( 'ashoka_faculty_marks' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Enter Marks', 'ashoka-exam-section' ); ?></h1>

            <!-- Filter form -->
            <form method="post" id="ashoka-marks-filter-form" style="background:#fff;padding:15px;margin-bottom:20px;border:1px solid #ccd0d4;">
                <?php wp_nonce_field( 'ashoka_marks_filter' ); ?>
                <table class="form-table" style="margin:0;">
                    <tr>
                        <th style="width:120px;"><?php esc_html_e( 'Subject', 'ashoka-exam-section' ); ?></th>
                        <td>
                            <select name="filter_sub_code" id="filter_sub_code" required>
                                <option value=""><?php esc_html_e( '— Select Subject —', 'ashoka-exam-section' ); ?></option>
                                <?php
                                $unique_subjects = array();
                                foreach ( $mappings as $m ) {
                                    if ( ! isset( $unique_subjects[ $m->sub_code ] ) ) {
                                        $unique_subjects[ $m->sub_code ] = $m->sub_name;
                                    }
                                }
                                foreach ( $unique_subjects as $code => $name ) :
                                    ?>
                                    <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $sel_sub_code, $code ); ?>><?php echo esc_html( $name . ' (' . $code . ')' ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <th style="width:100px;"><?php esc_html_e( 'Branch', 'ashoka-exam-section' ); ?></th>
                        <td>
                            <select name="filter_branch" id="filter_branch" required>
                                <option value=""><?php esc_html_e( '— Select Branch —', 'ashoka-exam-section' ); ?></option>
                                <?php
                                $unique_branches = array();
                                foreach ( $mappings as $m ) {
                                    $unique_branches[ $m->branch ] = $m->branch;
                                }
                                foreach ( $unique_branches as $b ) :
                                    ?>
                                    <option value="<?php echo esc_attr( $b ); ?>" <?php selected( $sel_branch, $b ); ?>><?php echo esc_html( $b ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <th style="width:80px;"><?php esc_html_e( 'Year', 'ashoka-exam-section' ); ?></th>
                        <td>
                            <select name="filter_year" id="filter_year" required>
                                <option value=""><?php esc_html_e( '— Select Year —', 'ashoka-exam-section' ); ?></option>
                                <?php foreach ( array( 'I Year', 'II Year', 'III Year', 'IV Year' ) as $y ) : ?>
                                    <option value="<?php echo esc_attr( $y ); ?>" <?php selected( $sel_year, $y ); ?>><?php echo esc_html( $y ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <th style="width:80px;"><?php esc_html_e( 'Sem', 'ashoka-exam-section' ); ?></th>
                        <td>
                            <select name="filter_sem" id="filter_sem" required>
                                <option value=""><?php esc_html_e( '— Select Sem —', 'ashoka-exam-section' ); ?></option>
                                <?php foreach ( array( 'I Sem', 'II Sem' ) as $s ) : ?>
                                    <option value="<?php echo esc_attr( $s ); ?>" <?php selected( $sel_sem, $s ); ?>><?php echo esc_html( $s ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <button type="submit" name="ashoka_marks_load" class="button button-primary"><?php esc_html_e( 'Load Students', 'ashoka-exam-section' ); ?></button>
                        </td>
                    </tr>
                </table>
            </form>

            <?php if ( $sel_sub_code && $sel_branch && $sel_year && $sel_sem ) : ?>
                <!-- Marks entry table -->
                <form method="post">
                    <?php wp_nonce_field( 'ashoka_faculty_marks_save' ); ?>
                    <input type="hidden" name="sub_code" value="<?php echo esc_attr( $sel_sub_code ); ?>">
                    <input type="hidden" name="branch" value="<?php echo esc_attr( $sel_branch ); ?>">
                    <input type="hidden" name="year" value="<?php echo esc_attr( $sel_year ); ?>">
                    <input type="hidden" name="sem" value="<?php echo esc_attr( $sel_sem ); ?>">

                    <table class="wp-list-table widefat fixed striped ashoka-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'SNO', 'ashoka-exam-section' ); ?></th>
                                <th><?php esc_html_e( 'Student Name', 'ashoka-exam-section' ); ?></th>
                                <th><?php esc_html_e( 'HTNo', 'ashoka-exam-section' ); ?></th>
                                <th><?php esc_html_e( 'MID-I (max 30)', 'ashoka-exam-section' ); ?></th>
                                <th><?php esc_html_e( 'MID-II (max 30)', 'ashoka-exam-section' ); ?></th>
                                <th><?php esc_html_e( 'Total', 'ashoka-exam-section' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( $students ) : ?>
                                <?php foreach ( $students as $i => $student ) : ?>
                                    <?php
                                    $m    = isset( $existing_marks[ $student->htno ] ) ? $existing_marks[ $student->htno ] : null;
                                    $mid1 = $m ? $m->mid1_marks : '';
                                    $mid2 = $m ? $m->mid2_marks : '';
                                    $tot  = $m ? $m->total_marks : '';
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html( $i + 1 ); ?></td>
                                        <td><?php echo esc_html( $student->student_name ); ?></td>
                                        <td>
                                            <?php echo esc_html( $student->htno ); ?>
                                            <input type="hidden" name="htno[]" value="<?php echo esc_attr( $student->htno ); ?>">
                                        </td>
                                        <td><input type="number" name="mid1[]" step="0.01" min="0" max="30" class="small-text ashoka-mid-input" value="<?php echo esc_attr( $mid1 ); ?>"></td>
                                        <td><input type="number" name="mid2[]" step="0.01" min="0" max="30" class="small-text ashoka-mid-input" value="<?php echo esc_attr( $mid2 ); ?>"></td>
                                        <td class="ashoka-total-cell"><?php echo esc_html( $tot ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr><td colspan="6"><?php esc_html_e( 'No students found for the selected combination.', 'ashoka-exam-section' ); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if ( $students ) : ?>
                        <p class="submit">
                            <button type="submit" name="ashoka_faculty_marks_save" class="button button-primary"><?php esc_html_e( 'Save All Marks', 'ashoka-exam-section' ); ?></button>
                        </p>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    // ----------------------------------------------------------------
    // AJAX: get students for a given combination
    // ----------------------------------------------------------------
    public static function ajax_get_students_for_marks() {
        check_ajax_referer( 'ashoka_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'ashoka_enter_marks' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'ashoka-exam-section' ) );
        }

        global $wpdb;
        $sub_code = sanitize_text_field( wp_unslash( $_POST['sub_code'] ?? '' ) );
        $branch   = sanitize_text_field( wp_unslash( $_POST['branch'] ?? '' ) );
        $year     = sanitize_text_field( wp_unslash( $_POST['year'] ?? '' ) );
        $sem      = sanitize_text_field( wp_unslash( $_POST['sem'] ?? '' ) );

        $students_tbl = $wpdb->prefix . 'ashoka_students';
        $marks_tbl    = $wpdb->prefix . 'ashoka_internal_marks';

        $students = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $students_tbl WHERE branch = %s AND year = %s AND sem = %s ORDER BY student_name ASC",
            $branch, $year, $sem
        ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $marks_raw = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $marks_tbl WHERE sub_code = %s AND branch = %s AND year = %s AND sem = %s",
            $sub_code, $branch, $year, $sem
        ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $existing = array();
        foreach ( $marks_raw as $m ) {
            $existing[ $m->student_htno ] = $m;
        }

        $rows = array();
        foreach ( $students as $s ) {
            $m      = isset( $existing[ $s->htno ] ) ? $existing[ $s->htno ] : null;
            $rows[] = array(
                'htno'         => $s->htno,
                'student_name' => $s->student_name,
                'mid1'         => $m ? $m->mid1_marks : '',
                'mid2'         => $m ? $m->mid2_marks : '',
                'total'        => $m ? $m->total_marks : '',
            );
        }

        wp_send_json_success( $rows );
    }
}

