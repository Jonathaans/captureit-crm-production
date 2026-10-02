@pushOnce('scripts')
    <script>
        window.normalizeSalesLineItem = (item) => {
            const line = { ...item };
            const quantity = Number(line.quantity) || 0;

            if (! ['pcs', 'day'].includes(line.unit)) {
                const days = Math.max(1, Number.parseInt(line.day, 10) || 1);
                line.quantity = quantity * days;
                line.unit = days > 1 ? 'day' : 'pcs';
            }

            line.day = 1;

            return line;
        };
    </script>
@endPushOnce
