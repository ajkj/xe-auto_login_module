jQuery(document).ready(function(){
    jQuery('#auto_login_cookie_encryption_password_generate').click(function(e){
        e.preventDefault();
        if(jQuery('#auto_login_cookie_encryption_password').val() !== ''){
            if(confirm('기존의 비밀번호를 변경 하시겠습니까? 비밀번호를 변경시 기존에 기록하던 스마트 자동로그인 기록이 무효화 됩니다.')===false){
                e.cancel();
                return;
            }
        }
        var list = 'abcdefghkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ123456789!@#$%^&*()[]{}<>?_=+';
        var i=0;
        var n ='';
        for(i=0; i<22; i++){
            n += list.charAt(SecureNormalRandom(0, list.length-1));
        }
        jQuery('#auto_login_cookie_encryption_password').val(n);
    })
})