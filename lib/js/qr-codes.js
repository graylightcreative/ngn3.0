function getQRCodes(){
    const axiosInstance = axios.create({
        baseURL: 'https://me-qr.com/api', // Replace with your API base URL
        headers: {
            'Accept': 'application/json',
            'X-AUTH-TOKEN': '0df786239c037891d69fb5d40b80bc0120e189bfbdd31a25249e82a0af867368' // Replace with the actual API key
        }
    });

// Example usage
    axiosInstance.get('/qr/list') // Adjusted to match the correct endpoint structure
        .then(response => {
            console.log(response.data); // Handle the response data
        })
        .catch(error => {
            console.error(error.response?.data || error.message); // Improved error handling
        });
}