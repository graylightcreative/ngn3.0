$(document).ready(function() {
    // Delegate the 'input' event to any existing or future elements with id 'title'
    $(document).on('input', '#title', function() {
        let title = $(this).val();
        let slug = title.toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/^-+|-+$/g, '');

        $('#slug').val(slug);
    });
});