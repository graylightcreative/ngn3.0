$(document).ready(function() {
    let baseurl = $('#baseurl').val();
    // $('button').click(function() {
    //     if (!$(this).hasClass('no-loading')) {
    //         showLoading($(this)); // Unleash the spinner!
    //     }
    // });

    $('.advertisement-link').click(function(e){
        e.preventDefault();


        let href= $(this).attr('href');

        var hasCalloutParent = $(this).closest('#callout').length > 0;
        let hitAction = '';

        if (hasCalloutParent) {
            hitAction = 'callout_click';
        } else {
            hitAction = 'ad_click';
        }

        // get current page
        // add hit for ad_click from current page
        let currentPage = $(location).prop('href');
        axios.post('https://nextgennoise.com/lib/handlers/add-ad-hit.php',{
            "action":hitAction,
            "current_page":currentPage,
            "href":href
        })
            .then((res)=>{
                if(res.data){
                    if(res.data.success){
                        window.location.href=href;
                    } else {
                        alert(res.data.message);
                        console.log(res.data);
                    }
                } else {
                    alert('An issue occurred');
                }
            })
    })
    $('.popup-toggle').click(function(e){
        e.preventDefault();
        let popup = $(this).data('popup');
        $('#'+popup).slideDown('slow',function(){
            console.log('Opened #'+popup);
        });



    })

    $('.close-popup').click(function(e){
        e.preventDefault();
        let parent = $(this).parent();
        parent.fadeOut();
        finishLoading($('*')) // remove loading from anything that may have it
    })

    $('.btn').not('.no-loading').click(function(e){
        let color;
        ($(this).hasClass('btn-primary')) ? color = 'dark' : color = 'primary';
        let size = '-sm';
        if($(this).hasClass('btn-sm')){
            size = '-sm';
        } else if($(this).hasClass('btn-lg')){
            size = '-lg';
        }

        const $spinner = makeSpinner(size, color);
        $(this).prepend($spinner);

    });

    // $('.validate-form').click(function (e){
    //     e.preventDefault();
    //
    //     let parentForm = $(this).closest('form'); // Get the closest parent form
    //     let validationResult = validateBootstrapForm(parentForm); // Call the existing helper function to validate
    //
    //     if (validationResult.isValid) {
    //         // If form is valid, you may proceed with your logic here
    //         console.log("Form is valid. FormData:", validationResult.formData);
    //     } else {
    //         // If form is invalid, optionally add logic to notify the user here
    //         console.log("Form is invalid. Fix the highlighted fields.");
    //     }
    // })
});

function openPopup(el){
    let popup = $(el).data('popup');
    $('#'+popup).slideDown('slow',function(){
        // console.log('Opened #'+popup);
    });
}

function validateFormElement(formElement) {
    const formField = $(formElement);

    // Reset any previous validation states
    formField.removeClass('is-valid is-invalid');

    // Trigger browser's own validation
    formField[0].reportValidity();

    // Check and set validity classes based on the field's validity
    if (formField[0].checkValidity()) {
        formField.addClass('is-valid');
        return true;
    } else {
        formField.addClass('is-invalid');
        return false;
    }
}

function makeSpinner(size='',color='primary'){
    return $('<div>', {
        class: `spinner-grow bg-${color} spinner-grow${size}`,
        role: 'status',
        html: '<span class="visually-hidden">Loading...</span>'
    });
}
function startLoading(el){
    el.prepend(makeSpinner('-sm'));
    el.prop('disabled',true);
}
function finishLoading(el){
    el.find('.spinner-grow').fadeOut('slow',function(){
        $('.spinner-grow').remove();
    })
    el.prop('disabled',false);
}
function removeFormChecks(){
    $('.is-invalid').removeClass('is-invalid');
    $('.is-valid').removeClass('is-valid');
}
function createToast(message, type = "success", options = {}) {
    // Create the toast container
    const toastContainer = $("<div>")
        .addClass("toast align-items-center")
        .attr({
            role: "alert",
            "aria-live": "assertive",
            "aria-atomic": "true",
            "data-bs-autohide": "true"
        });

    // Set custom data attribute for styling and options
    toastContainer.attr("data-toast-type", type); // Add a custom data attribute for the type

    // Create the toast header (optional)
    const toastHeader = $("<div>")
        .addClass("toast-header");

    // Icon based on the type (customize icons)
    const toastIcon = $("<i>")
        .addClass("bi") // Bootstrap icons
        .addClass(type === "success" ? "bi-check-circle-fill" :
            type === "error" ? "bi-exclamation-circle-fill" :
                type === "warning" ? "bi-exclamation-triangle-fill" :
                    "bi-info-circle-fill");

    toastHeader.append(toastIcon).append(` <strong class="me-auto">${type.charAt(0).toUpperCase() + type.slice(1)}</strong>`);

    // Close button
    const closeButton = $("<button>")
        .addClass("btn-close")
        .attr({
            type: "button",
            "data-bs-dismiss": "toast",
            "aria-label": "Close",
        });

    toastHeader.append(closeButton);

    // Create the toast body
    const toastBody = $("<div>").addClass("toast-body").text(message);

    // Assemble the toast
    toastContainer.append(toastHeader).append(toastBody);

    // Append to the body or a specific container
    $("body").append(toastContainer);

    // Initialize the toast with options
    const toast = new bootstrap.Toast(toastContainer[0], options);
    toast.show();

    return toast; // Return the toast instance for further control if needed
}
function validateBootstrapForm(form) {
    // Force browser to re-check validity (important!)
    $(form)[0].reportValidity();

    $(form).removeClass('was-validated');
    $(form).find('.invalid-feedback').remove();
    $(form).find('.is-invalid').removeClass('is-invalid');

    let isValid = $(form)[0].checkValidity(); // Check validity after browser's check
    let formData = {};

    $(form).find('input, select, textarea').each(function() {
        const name = $(this).attr('name');
        const value = $(this).val();
        formData[name] = value;

        if (!this.checkValidity()) {
            isValid = false;
            $(this).addClass('is-invalid');
            const feedbackMessage = this.validationMessage || "Please fill out this field.";
            $(this).after(`<div class="invalid-feedback">${feedbackMessage}</div>`);
        }
    });

    return { isValid: isValid, formData: formData };
}