<?php
/**
 * Role and capability management.
 *
 * @package Ashoka_Exam_Section
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ashoka_Roles {

    /**
     * Create custom roles on activation.
     */
    public static function create_roles() {
        // Data Operator role.
        add_role(
            'ashoka_data_operator',
            __( 'Ashoka Data Operator', 'ashoka-exam-section' ),
            array(
                'read'                    => true,
                'ashoka_manage_students'  => true,
                'ashoka_manage_subjects'  => true,
                'ashoka_manage_faculty'   => true,
                'ashoka_manage_branches'  => true,
                'ashoka_manage_mapping'   => true,
                'ashoka_manage_marks'     => true,
                'ashoka_export_data'      => true,
                'ashoka_import_data'      => true,
            )
        );

        // Faculty role.
        add_role(
            'ashoka_faculty',
            __( 'Ashoka Faculty', 'ashoka-exam-section' ),
            array(
                'read'                  => true,
                'ashoka_view_students'  => true,
                'ashoka_export_data'    => true,
                'ashoka_enter_marks'    => true,
            )
        );

        // Give administrators all Ashoka capabilities.
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $caps = array(
                'ashoka_manage_students',
                'ashoka_manage_subjects',
                'ashoka_manage_faculty',
                'ashoka_manage_branches',
                'ashoka_manage_mapping',
                'ashoka_manage_marks',
                'ashoka_export_data',
                'ashoka_import_data',
                'ashoka_delete_data',
                'ashoka_view_students',
                'ashoka_enter_marks',
            );
            foreach ( $caps as $cap ) {
                $admin->add_cap( $cap );
            }
        }
    }

    /**
     * Remove custom roles on uninstall.
     */
    public static function remove_roles() {
        remove_role( 'ashoka_data_operator' );
        remove_role( 'ashoka_faculty' );

        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $caps = array(
                'ashoka_manage_students',
                'ashoka_manage_subjects',
                'ashoka_manage_faculty',
                'ashoka_manage_branches',
                'ashoka_manage_mapping',
                'ashoka_manage_marks',
                'ashoka_export_data',
                'ashoka_import_data',
                'ashoka_delete_data',
                'ashoka_view_students',
                'ashoka_enter_marks',
            );
            foreach ( $caps as $cap ) {
                $admin->remove_cap( $cap );
            }
        }
    }

    /**
     * Check if the current user can delete Ashoka data.
     */
    public static function can_delete() {
        return current_user_can( 'manage_options' ) || current_user_can( 'ashoka_delete_data' );
    }
}
