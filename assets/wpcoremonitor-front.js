var countdownInterval;

function customRedirect(count) {
    document.getElementById("countdown").innerText = count;

    if (count > 0) {
        countdownInterval = setTimeout(function () {
            customRedirect(count - 1);
        }, 1000);
    } else {
        var redirectLink = document.getElementById("redirect-link");
        var targetUrl = redirectLink.getAttribute("data-url");
        window.location.href = targetUrl;
    }
}

customRedirect(20); // Start the countdown

function stopRedirect() {
    clearTimeout(countdownInterval);
}