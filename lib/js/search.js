// AUTOCOMPLETE SEARCH SITE-WIDE

let searchCanvas = $('#searchCanvas'); // input
let searchResultsCanvas = $('#searchResultsCanvas'); // results

// This search will take [TERM]
let term;
searchCanvas.on('keyup',function(e){
    e.preventDefault;
    term = $(this).val();

    if(term !== '' && term !== null){
        let n =$(baseurl).val();
        axios.post(n+'lib/handlers/site-search.php',{term:term})
            .then((searchResponse)=>{
                if(searchResponse.data){
                    console.log(searchResponse.data);
                    if(searchResponse.data.success){
                        searchResultsCanvas.html(searchResponse.data.content);
                    }
                } else {
                    console.error(searchResponse.data);
                    searchResultsCanvas.html('<p>An known error has occurred. Please try again later.</p>');
                }
            })
    }
})