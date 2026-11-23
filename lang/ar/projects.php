<?php

return [
    // Projects
    'created_successfully' => 'تم إنشاء المشروع بنجاح.',
    'updated_successfully' => 'تم تحديث المشروع بنجاح.',
    'archived_successfully' => 'تمت أرشفة المشروع بنجاح.',
    'unarchived_successfully' => 'تم إلغاء أرشفة المشروع بنجاح.',
    'deleted_successfully' => 'تم حذف المشروع بنجاح.',
    'not_found' => 'المشروع غير موجود.',
    'forbidden' => 'ليس لديك صلاحية لإدارة هذا المشروع.',
    'archived_modification_prohibited' => 'لا يمكن تعديل مشروع مؤرشف.',
    'invalid_dates' => 'يجب أن يكون الموعد النهائي تاريخاً بعد تاريخ البدء أو مساوياً له.',
    'status_archived_not_allowed' => 'لا يمكن أرشفة المشروع من خلال التعديل العادي. استخدم نقطة نهاية الأرشفة.',

    // Members
    'member_added_successfully' => 'تمت إضافة عضو المشروع بنجاح.',
    'member_role_updated_successfully' => 'تم تحديث دور عضو المشروع بنجاح.',
    'member_removed_successfully' => 'تمت إزالة عضو المشروع بنجاح.',
    'member_already_exists' => 'المستخدم عضو بالفعل في هذا المشروع.',
    'user_not_project_member' => 'المستخدم ليس عضواً في هذا المشروع.',
    'cannot_add_suspended_user' => 'لا يمكن إضافة مستخدم معلق إلى المشروع.',
    'cannot_remove_last_project_manager' => 'لا يمكن إزالة آخر مدير للمشروع.',
    'cannot_demote_last_project_manager' => 'لا يمكن إزالة آخر مدير للمشروع.',

    // Teams
    'team_created_successfully' => 'تم إنشاء الفريق بنجاح.',
    'team_updated_successfully' => 'تم تحديث الفريق بنجاح.',
    'team_deleted_successfully' => 'تم حذف الفريق بنجاح.',
    'team_not_in_project' => 'الفريق لا ينتمي إلى هذا المشروع.',
    'team_name_already_exists' => 'يوجد فريق بهذا الاسم بالفعل في هذا المشروع.',
    'team_leader_must_be_project_member' => 'يجب أن يكون قائد الفريق عضواً بالفعل في المشروع.',
    'team_member_must_be_project_member' => 'يجب أن يكون المستخدم عضواً في المشروع قبل الانضمام إلى الفريق.',
    'team_member_added_successfully' => 'تمت إضافة عضو الفريق بنجاح.',
    'team_member_removed_successfully' => 'تمت إزالة عضو الفريق بنجاح.',
    'team_member_already_exists' => 'المستخدم عضو بالفعل في هذا الفريق.',
    'team_member_not_found' => 'المستخدم ليس عضواً في هذا الفريق.',

    // Handover & Suspension
    'user_suspended_successfully' => 'تم تعليق حساب المستخدم بنجاح.',
    'handover_required' => 'التسليم مطلوب: المستخدم هو مدير المشروع النشط الوحيد في مشروع أو أكثر.',
    'handover_completed_successfully' => 'تم التسليم وتعليق المستخدم بنجاح.',
    'invalid_replacement_user' => 'المستخدم البديل المحدد غير صالح.',
    'replacement_cannot_be_self' => 'لا يمكن أن يكون المستخدم البديل هو نفس المستخدم المعلق.',
    'replacement_is_suspended' => 'المستخدم البديل معلق ولا يمكن تعيينه كمدير مشروع.',
    'replacement_project_mismatch' => 'قائمة مشاريع التسليم لا تتطابق مع المشاريع المطلوبة.',
    'duplicate_handover_project' => 'تم تحديد مشروع تسليم مكرر.',
    'admin_suspension_only' => 'فقط مسؤولو النظام يمكنهم تعليق حسابات المستخدمين.',

    // Unexpected 500 Errors
    'unexpected_error_create_project' => 'حدث خطأ غير متوقع أثناء إنشاء المشروع.',
    'unexpected_error_update_project' => 'حدث خطأ غير متوقع أثناء تحديث المشروع.',
    'unexpected_error_archive_project' => 'حدث خطأ غير متوقع أثناء أرشفة المشروع.',
    'unexpected_error_unarchive_project' => 'حدث خطأ غير متوقع أثناء إلغاء أرشفة المشروع.',
    'unexpected_error_delete_project' => 'حدث خطأ غير متوقع أثناء حذف المشروع.',
    'unexpected_error_add_member' => 'حدث خطأ غير متوقع أثناء إضافة عضو المشروع.',
    'unexpected_error_update_member' => 'حدث خطأ غير متوقع أثناء تحديث دور العضو.',
    'unexpected_error_remove_member' => 'حدث خطأ غير متوقع أثناء إزالة عضو المشروع.',
    'unexpected_error_create_team' => 'حدث خطأ غير متوقع أثناء إنشاء الفريق.',
    'unexpected_error_update_team' => 'حدث خطأ غير متوقع أثناء تحديث الفريق.',
    'unexpected_error_delete_team' => 'حدث خطأ غير متوقع أثناء حذف الفريق.',
    'unexpected_error_add_team_member' => 'حدث خطأ غير متوقع أثناء إضافة عضو الفريق.',
    'unexpected_error_remove_team_member' => 'حدث خطأ غير متوقع أثناء إزالة عضو الفريق.',
    'unexpected_error_suspend_user' => 'حدث خطأ غير متوقع أثناء تعليق حساب المستخدم.',

    // Slice 3 Tasks
    'task_created_successfully' => 'تم إنشاء المهمة بنجاح.',
    'task_updated_successfully' => 'تم تحديث المهمة بنجاح.',
    'task_status_updated_successfully' => 'تم تحديث حالة المهمة بنجاح.',
    'task_deleted_successfully' => 'تم حذف المهمة بنجاح.',
    'task_not_found' => 'المهمة غير موجودة.',
    'task_not_in_project' => 'المهمة لا تنتمي إلى هذا المشروع.',
    'unexpected_error_create_task' => 'حدث خطأ غير متوقع أثناء إنشاء المهمة.',
    'unexpected_error_update_task' => 'حدث خطأ غير متوقع أثناء تحديث المهمة.',
    'unexpected_error_delete_task' => 'حدث خطأ غير متوقع أثناء حذف المهمة.',

    // Slice 3 Files
    'file_uploaded_successfully' => 'تم رفع الملف بنجاح.',
    'file_deleted_successfully' => 'تم حذف الملف بنجاح.',
    'file_not_found' => 'الملف غير موجود.',
    'file_not_in_project' => 'الملف لا ينتمي إلى هذا المشروع.',
    'file_not_found_on_disk' => 'الملف المطلوب غير موجود على وحدة التخزين.',
    'unexpected_error_upload_file' => 'حدث خطأ غير متوقع أثناء رفع الملف.',
    'unexpected_error_download_file' => 'حدث خطأ غير متوقع أثناء تحميل الملف.',
    'unexpected_error_delete_file' => 'حدث خطأ غير متوقع أثناء حذف الملف.',
];
