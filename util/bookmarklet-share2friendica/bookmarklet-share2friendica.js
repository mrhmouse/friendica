javascript:(function(){f='https://YourFriendicaDomain.tld/bookmarklet/?url='+encodeURIComponent(window.location.href)+'&title='+encodeURIComponent(document.title);a=function(){if(!window.open(f+'&jump=doclose','friendica','location=yes,links=no,scrollbars=no,toolbar=no,width=620,height=250'))location.href=f+'jump=yes'};if(/Firefox/.test(navigator.userAgent)){setTimeout(a,0)}else{a()}})()