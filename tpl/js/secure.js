window.SecureNormalRandom = (function(){
    "use strict";
    if(typeof window.crypto.getRandomValues === 'function'){
        return function(min,max){
            var a = new Uint32Array(1);
            window.crypto.getRandomValues(a);
            var gap = max - min;
            return (a[0] % gap) + min;
        }

    }else if(typeof window.mscrypto.getRandomValues === 'function'){
        return function(min,max){
            var a = new Uint32Array(1);
            window.mscrypto.getRandomValues(a);
            var gap = max - min;
            return (a[0] % gap) + min;
        }
    }else{
        return function(min, max){
            var a = window.Math.random()+'';
            a = a.substr(a.length-9, 9);
            a = window.parseInt(a);
            var gap = max - min;
            return (a % gap) + min;
        }
    }
})();