let playlist = $('#playlistSongList').text();
playlist = JSON.parse(playlist);

let slug = $('.user-slug').val();
let canvas = $('.audio-canvas');
let play = $('.audio-canvas .play');
let progressBar = $('.audio-canvas .progress-bar');
let timeCode = $('.audio-canvas .time-code');
let volumeToggle = $('.audio-canvas .volume-toggle');
let canvasImage = $('.audio-canvas .audio-canvas-image');

// Initialize Web Audio API
let audioContext = new (window.AudioContext || window.webkitAudioContext)();
let audioElement = document.createElement('audio');



try {
    if (playlist.length === 0 || !playlist[0].mp3) {
        throw new Error('Playlist does not contain valid audio files.');
    }
    audioElement.src = 'https://nextgennoise.com/lib/uploads/'+slug+'/'+playlist[0].mp3;
} catch (e) {
    console.error('Error parsing playlist or setting audio source:', e);
}

let track = audioContext.createMediaElementSource(audioElement);
let gainNode = audioContext.createGain();
track.connect(gainNode).connect(audioContext.destination);

// Play button functionality
play.on('click', function () {
    if (!audioElement.src) {
        alert('No valid audio source found.');
        return;
    }

    if (audioContext.state === 'suspended') {
        audioContext.resume();
    }

    if (audioElement.paused) {
        audioElement.play().catch((error) => {
            console.error('Error playing audio:', error);
            alert('Error playing audio. Please try again.');
        });
        play.html('<i class="bi bi-pause"></i>'); // Update UI to show "Pause"
    } else {
        audioElement.pause();
        play.html('<i class="bi bi-play"></i>'); // Update UI to show "Play"
    }
});

// Update progress bar and time code
audioElement.addEventListener('timeupdate', function () {
    if (!isNaN(audioElement.duration)) {
        let progress = (audioElement.currentTime / audioElement.duration) * 100;
        progressBar.css('width', progress + '%');
        let minutes = Math.floor(audioElement.currentTime / 60);
        let seconds = Math.floor(audioElement.currentTime % 60);
        timeCode.text(`${minutes}:${seconds < 10 ? '0' + seconds : seconds}`);
    }
});

// Volume toggle functionality
volumeToggle.on('click', function () {
    if($(this).hasClass('muted')) {
        gainNode.gain.value = 1;
        volumeToggle.toggleClass('muted', gainNode.gain.value === 0); // Add 'muted' class if muted
        volumeToggle.find('i').remove()
        volumeToggle.html('<i class="bi bi-volume-up-fill"></i>').removeClass('btn-danger').addClass('btn-outline-secondary'); // Update icon to mute/unmute
    } else {
        gainNode.gain.value = gainNode.gain.value > 0 ? 0 : 1;
        volumeToggle.toggleClass('muted', gainNode.gain.value === 0); // Add 'muted' class if muted
        volumeToggle.find('i').remove()
        volumeToggle.html('<i class="bi bi-volume-mute-fill"></i>').removeClass('btn-outline-secondary').addClass('btn-danger'); // Update icon to mute/unmute
    }

});

// Ensure audio canvas image is reactive (e.g., changes on play)
audioElement.addEventListener('play', function () {
    canvasImage.addClass('playing');
});

audioElement.addEventListener('pause', function () {
    canvasImage.removeClass('playing');
});