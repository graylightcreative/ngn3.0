
// document.addEventListener('DOMContentLoaded', function () {
//     // Check Subscription Status and Display Popup Accordingly
//     if (!localStorage.getItem('mailingSubscribed')) {
//         if(!sessionStorage.getItem('newsletterSubscribePopupClosed')){
//             $('#contactJoinPopup').css('display', 'block');
//         }
//     }
//
//     // Close button functionality
//     $('#contactJoinPopup .close-popup').click(function () {
//         $('#contactJoinPopup').css('display', 'none');
//         sessionStorage.setItem('newsletterSubscribePopupClosed', 'true');
//     });
//
//     // Close popup if user clicks outside of it
//     $(window).click(function(event) {
//         if (event.target == document.getElementById('newsletterSignupPopup')) {
//             $('#contactJoinPopup').css('display', 'none');
//         }
//     });
// });

// Event listener for the newsletter signup button click
$('.newsletterSignup button').click(function(e) {
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
                    // console.log('got response');
                    // console.log(res.data);
                    if(res.data.success){
                        alert('Thank you for joining!');
                        // Storing the subscription status in localStorage
                        localStorage.setItem('mailingSubscribed', 'true');
                        location.reload();
                    } else {
                        alert(res.data.message);
                        if(res.data.code===400){
                            localStorage.setItem('mailingSubscribed', 'true');
                            location.reload();
                        } else {
                            localStorage.setItem('mailingSubscribed', 'false');

                            alert(res.data.message);
                            console.error(res.data);
                        }
                    }
                } else {
                    localStorage.setItem('mailingSubscribed', 'false');

                    alert('An unknown error has occurred');
                    console.error(res.data);
                }
            });
    } else {
        finishLoading($(this));
    }
});

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