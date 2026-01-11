// سلوكيات front-end للإضافة: إرسال واجب، تقييم أسئلة (AJAX)
jQuery(function($){
    $(document).on('submit','#wpedu-submit-assignment', function(e){
        e.preventDefault();
        var $f = $(this);
        $.post(ajaxurl, $f.serialize(), function(res){
            if (res.success) { alert('تم التسليم بنجاح'); location.reload(); } else { alert(res.data || 'حدث خطأ'); }
        });
    });
    // دالة تحميل الشهادة عبر رابط العام (فتح جديد)
});