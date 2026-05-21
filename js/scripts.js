"use strict";

function main() {

    var topButton = document.getElementById('topButton-phpinfo-WP');

    function showButton() {
        if (topButton !== null) {
            if (document.body.scrollTop > 400 || document.documentElement.scrollTop > 400) {
                topButton.style.display = "block";
            } else {
                topButton.style.display = "none";
            }
        }
    }

    window.onscroll = function () {
        showButton();
    };

    function goTop() {
        document.body.scrollTop = 0;
        document.documentElement.scrollTop = 0;
    }

    if (topButton !== null) {
        topButton.addEventListener('click', goTop);
    }

}

main();
