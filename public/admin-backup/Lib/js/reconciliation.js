$('#reconcileArtists').click(function(e){
    e.preventDefault();
    let canvas = $('#artistReconciliationCanvas');
    canvas.html(makeSpinner());
    axios.post('lib/handlers/get-unadded-artists.php')
        .then((res)=>{
            if(res.data){
                console.log(res.data);
                if(res.data.success){
                    $('#artistReconciliationCanvas').html(res.data.content);
                    $('.popup-toggle').click(function(e){
                        // fill out add user popup form
                        let artist = ($(this).data('title') ?? 'WHAT').split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()).join(' ');
                        let source = $(this).data('type');
                        let type = 'artist';
                        $('#addUserTitle').val(artist);
                        $('#addUserSource').val(source);
                        $('#addUserType').val(type);
                        openPopup($(this));
                    })
                } else {
                    alert(res.data.message);
                }
            } else {
                alert('Unknown error');
            }
        })
})
$('#reconcileLabels').click(function(e){
    e.preventDefault();

    axios.post('lib/handlers/get-unadded-labels.php')
        .then((res)=>{
            if(res.data){
                if(res.data.success){
                    $('#labelsReconciliationCanvas').html(res.data.content);
                } else {
                    alert(res.data.message);
                }
            } else {
                alert('Unknown error');
            }
        })
})