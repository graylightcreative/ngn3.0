$('.single-carousel').slick({
    autoplay: true,
    autoplaySpeed: 5000,
    arrows:false,
    dots:false,
    adaptiveHeight: true,
});

$('.single-fade-carousel').slick({
    autoplay: true,
    autoplaySpeed: 5000,
    fade:true,
    arrows:false,
    dots:false,
    adaptiveHeight: true,
});

$('.recent-releases').slick({
    autoplay: true,
    autoplaySpeed: 5000,
    fade:false,
    arrows:true,
    dots:false,
    adaptiveHeight: true,
    slidesToShow: 5,
    slidesToScroll: 1,
    centerMode: true,
    scrollToSlide: true,
    nextArrow: $('.recent-releases-next'),
    prevArrow: $('.recent-releases-prev'),
    responsive: [
        {
            breakpoint: 1024,
            settings: {
                slidesToShow: 3,
            }
        },
        {
            breakpoint: 600,
            settings: {
                slidesToShow: 2,
                centerMode: false,
            }
        },
        {
            breakpoint: 480,
            settings: {
                slidesToShow: 1,
                centerMode: false
            }
        }
    ]
});
$('.recent-videos-carousel').slick({
    autoplay: true,
    autoplaySpeed: 5000,
    fade:false,
    arrows:true,
    dots:false,
    adaptiveHeight: true,
    slidesToShow: 5,
    slidesToScroll: 1,
    centerMode: true,
    scrollToSlide: true,
    nextArrow: $('.recent-videos-next'),
    prevArrow: $('.recent-videos-prev'),
    responsive: [
        {
            breakpoint: 1024,
            settings: {
                slidesToShow: 3,
            }
        },
        {
            breakpoint: 600,
            settings: {
                slidesToShow: 2,
                centerMode: false,
            }
        },
        {
            breakpoint: 480,
            settings: {
                slidesToShow: 1,
                centerMode: false
            }
        }
    ]
});


$('.top-artists-carousel').slick({
    autoplay: true,
    autoplaySpeed: 5000,
    fade:false,
    arrows:true,
    dots:false,
    adaptiveHeight: true,
    slidesToShow: 5,
    slidesToScroll: 1,
    centerMode: true,
    scrollToSlide: true,
    nextArrow: $('.top-artists-next'),
    prevArrow: $('.top-artists-prev'),
    responsive: [
        {
            breakpoint: 1024,
            settings: {
                slidesToShow: 3,
            }
        },
        {
            breakpoint: 600,
            settings: {
                slidesToShow: 2,
                centerMode: false,
            }
        },
        {
            breakpoint: 480,
            settings: {
                slidesToShow: 1,
                centerMode: false
            }
        }
    ]
});
$('.top-labels-carousel').slick({
    autoplay: true,
    autoplaySpeed: 5000,
    fade:false,
    arrows:true,
    dots:false,
    adaptiveHeight: true,
    slidesToShow: 5,
    slidesToScroll: 1,
    centerMode: true,
    scrollToSlide: true,
    nextArrow: $('.top-labels-next'),
    prevArrow: $('.top-labels-prev'),
    responsive: [
        {
            breakpoint: 1024,
            settings: {
                slidesToShow: 3,
                centerMode: true,
            }
        },
        {
            breakpoint: 600,
            settings: {
                slidesToShow: 2,
                centerMode: false,
            }
        },
        {
            breakpoint: 480,
            settings: {
                slidesToShow: 1,
                centerMode: false
            }
        }
    ]
});