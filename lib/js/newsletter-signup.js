// === AUTO-TRIGGER LOGIC FOR EMAIL CAPTURE ===
// Check if we should auto-show the popup to non-logged-in users
(function() {
    // Don't show to logged-in users
    if (window.NGN && window.NGN.isAuthenticated) {
        return;
    }

    // Check if user already signed up
    if (localStorage.getItem('ngnEmailCaptured') === 'true') {
        return;
    }

    // Check if user dismissed recently (within 7 days)
    const dismissedAt = localStorage.getItem('ngnEmailDismissedAt');
    if (dismissedAt) {
        const dismissedTimestamp = parseInt(dismissedAt);
        const daysSinceDismissal = (Date.now() - dismissedTimestamp) / (1000 * 60 * 60 * 24);
        if (daysSinceDismissal < 7) {
            return; // Don't show if dismissed within last 7 days
        }
    }

    // Auto-show popup after 2-second delay
    setTimeout(function() {
        $('#contactJoinPopup').fadeIn('slow');
    }, 2000);
})();

// === FORM SUBMISSION HANDLER ===
$('.newsletterSignup button[type="submit"]').click(function(e) {
    e.preventDefault();
    startLoading($(this));

    // Validate the form
    let valid = validateForm($('.newsletterSignup'));
    if(valid){
        let fname = $('.newsletterFirstName').val();
        let lname = $('.newsletterLastName').val();
        let email = $('.newsletterEmail').val();
        let phone = $('.newsletterPhone').val();
        let birthday = $('.newsletterBirthday').val();
        let band = $('.newsletterBand').val();
        let baseurl = $('#baseurl').val();

        // Sending data to the server using axios
        axios.post(baseurl + 'lib/handlers/newsletter-signup.php',{
            first_name: fname,
            last_name: lname,
            email: email,
            phone: phone,
            birthday: birthday,
            band: band
        })
            .then((res) => {
                if(res.data){
                    if(res.data.success){
                        // Mark as captured in localStorage
                        localStorage.setItem('ngnEmailCaptured', 'true');
                        // Clear any dismissal timestamp
                        localStorage.removeItem('ngnEmailDismissedAt');

                        alert('Thank you for joining!');
                        // Close the popup
                        $('#contactJoinPopup').fadeOut();
                        // Reload page after a brief delay to show success state
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        alert(res.data.message);
                        if(res.data.code===400){
                            // Email already subscribed - mark as captured
                            localStorage.setItem('ngnEmailCaptured', 'true');
                            localStorage.removeItem('ngnEmailDismissedAt');
                            $('#contactJoinPopup').fadeOut();
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            console.error(res.data);
                            finishLoading($('.newsletterSignup button[type="submit"]'));
                        }
                    }
                } else {
                    alert('An unknown error has occurred');
                    console.error(res.data);
                    finishLoading($('.newsletterSignup button[type="submit"]'));
                }
            })
            .catch((error) => {
                alert('An error occurred. Please try again.');
                console.error(error);
                finishLoading($('.newsletterSignup button[type="submit"]'));
            });
    } else {
        finishLoading($(this));
    }
});

// === DISMISS BUTTON HANDLER ===
// Handle "Not now" dismiss button
$(document).on('click', '.dismiss-popup', function(e) {
    e.preventDefault();
    // Store dismissal timestamp
    localStorage.setItem('ngnEmailDismissedAt', Date.now().toString());
    $('#contactJoinPopup').fadeOut();
});

// === CLOSE BUTTON HANDLER ===
// Handle X close button (same as dismiss)
$(document).on('click', '#contactJoinPopup .close-popup', function() {
    localStorage.setItem('ngnEmailDismissedAt', Date.now().toString());
    $('#contactJoinPopup').fadeOut();
});

// === PHONE FORMATTER ===
// Formatter for phone input field
$('#newsletterPhone').on('input', function() {
    let inputValue = $(this).val().replace(/\D/g, ''); // Remove non-numeric characters

    if (inputValue.length > 10) {
        inputValue = inputValue.substring(0, 10); // Limit to 10 digits
    }

    let formattedValue = '';
    if (inputValue.length > 0) {
        formattedValue = '(' + inputValue.substring(0, 3);
    }
    if (inputValue.length > 3) {
        formattedValue += ') ' + inputValue.substring(3, 6);
    }
    if (inputValue.length > 6) {
        formattedValue += '-' + inputValue.substring(6, 10);
    }

    $(this).val(formattedValue);
});
