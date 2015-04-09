jQuery(document).ready(function(){
    jQuery('.auto_login_manager_element').find('form').each(function(a ,b){
        jQuery(b).submit(function(e){
            e.preventDefault();
            var act = jQuery(e.target).find('input[name="act"]').val();
            var module = jQuery(e.target).find('input[name="module"]').val();
            var auto_login_mapping = jQuery(e.target).find('input[name="auto_login_mapping"]').val();
            jQuery.exec_json(module+'.'+act, {'auto_login_mapping':auto_login_mapping},
                //success call back
                function(data){
                var a = jQuery(e.target).closest('.auto_login_manager_element');
                a.hide('slow', 'swing', function(){
                    a.remove();
                },
                // error callback
                function(err){

                });
            });
        });
    });
});
