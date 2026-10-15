@once
    @push('scripts')
        <script type="module">
            app.directive('directory-search', {
                mounted(root) {
                    const input = root.querySelector('[data-directory-search]');
                    const items = [...root.querySelectorAll('[data-directory-item]')];
                    const groups = [...root.querySelectorAll('[data-directory-group]')];
                    const empty = root.querySelector('[data-directory-empty]');
                    if (!input) return;
                    input.addEventListener('input', () => {
                        const search = input.value.trim().toLocaleLowerCase();
                        items.forEach(item => { item.hidden = !(item.dataset.search || item.textContent).toLocaleLowerCase().includes(search); });
                        groups.forEach(group => { group.hidden = ![...group.querySelectorAll('[data-directory-item]')].some(item => !item.hidden); });
                        if (empty) empty.hidden = items.some(item => !item.hidden);
                    });
                }
            });
        </script>
    @endpush
@endonce
