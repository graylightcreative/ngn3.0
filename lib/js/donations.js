$('#donate-checkout').click(function() {
    removeFormChecks();
    let selectedAmount = null;
    let donateEmail = $('#donateEmail');
    if(donateEmail.val()===''){
        donateEmail.addClass('is-invalid');
        return false;
    }
    let email = donateEmail;

    // Check if a preset amount is selected
    const selectedRadio = $('input[name="donation_amount"]:checked');
    if(selectedRadio.data('amount') === 'custom'){
        selectedAmount = prompt('How much do you wish to donate in dollar amount (15.00, 20.00, 20,75,75.00)?');
        selectedAmount = selectedAmount*100;
    } else {
        selectedAmount = selectedRadio.data('amount');
    }


    startLoading($(this));
    // Now you have the selectedAmount in cents, ready to be used in your Axios request
    const stripe = Stripe('pk_test_51L5CwXKLupXJT6OeNvYydMkuVwRh0pM8N4tvvtOPcsd7LoOZ1N9cP3I18GGjh2E39XXHZoMOgWgxKFmLXYRRiZEg00OhXJNqC5');


    axios.post(baseurl+'lib/handlers/create-checkout-session.php',{
        lineItems: [
            {
                price_data: {
                    currency: 'usd',
                    unit_amount: selectedAmount,
                    product_data: {
                        name: 'One-Time Donation',
                    },
                },
                quantity: 1,
            },
        ],
        success_url: 'https://nextgennoise.com/donations/success', // Replace with your actual success URL
        cancel_url: 'https://nextgennoise.com/donations/cancel', // Replace with your actual cancel URL
        // ... any other data needed for your specific Checkout configuration ...
    })
        .then((res)=>{
            if(res.data){
                if(res.data.session) {
                    axios.post(baseurl+'lib/handlers/store-transaction.php',{
                        session:res.data.session,
                        email:email
                    })
                        .then((r)=>{
                            if(r){
                                if(r.data){
                                    if(r.data.success){
                                        let session = res.data.session;
                                        window.location.href=session.url;
                                    } else {
                                        alert(r.data.message);
                                        console.error(r.data);
                                    }
                                } else {
                                    alert('An unknown error has occurred. Please try again later.');
                                    console.error(r.data);
                                }
                            }
                        })


                } else {
                    alert('Something went wrong in stripe land');
                }
            } else {
                alert('Something went wrong in axios land');
            }
        })

    finishLoading($(this));
    // ... (Rest of your Axios code to create the Checkout session) ...




});