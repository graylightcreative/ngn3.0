<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('global-search-input');
    const autocomplete = document.getElementById('search-autocomplete');
    const resultsBox = document.getElementById('autocomplete-results');
    let debounceTimer;

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const query = this.value.trim();

            if (query.length < 2) {
                autocomplete.classList.add('hidden');
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch(`/api/v1/search/suggest?q=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.data.length > 0) {
                            renderResults(data.data);
                            autocomplete.classList.remove('hidden');
                        } else {
                            autocomplete.classList.add('hidden');
                        }
                    })
                    .catch(() => autocomplete.classList.add('hidden'));
            }, 300);
        });
    }

    function renderResults(items) {
        resultsBox.innerHTML = items.map(item => {
            const icon = item.type === 'artist' ? 'bi-person-circle' : (item.type === 'label' ? 'bi-record-circle' : 'bi-music-note-beamed');
            const url = `/${item.type}/${item.slug || item.id}`;
            const img = item.image_url || '/lib/images/site/2026/default-avatar.png';
            const subtext = item.subtext || (item.type.charAt(0).toUpperCase() + item.type.slice(1));

            return '<a href="' + url + '" class="flex items-center gap-4 px-4 py-3 hover:bg-white/5 transition-colors group">' +
                    '<div class="w-10 h-10 rounded-lg overflow-hidden bg-zinc-800 flex-shrink-0">' +
                        '<img src="' + img + '" class="w-full h-full object-cover" onerror="this.src='/lib/images/site/default-avatar.png'">' +
                    '</div>' +
                    '<div class="flex-1 min-w-0">' +
                        '<div class="text-sm font-bold text-white truncate group-hover:text-brand transition-colors">' + item.name + '</div>' +
                        '<div class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest truncate">' + subtext + '</div>' +
                    '</div>' +
                    '<i class="bi ' + icon + ' text-zinc-600 group-hover:text-brand transition-colors"></i>' +
                '</a>';
        }).join('');
    }

    // Close on click outside
    document.addEventListener('click', function(e) {
        const searchForm = document.getElementById('global-search-form');
        if (searchForm && !searchForm.contains(e.target)) {
            autocomplete.classList.add('hidden');
        }
    });
});
</script>
