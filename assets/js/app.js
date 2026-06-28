$(function () {
    $('[data-stand-json]').on('click', function (event) {
        event.stopPropagation();
        const payload = JSON.parse($(this).attr('data-stand-json') || '{}');
        if (!payload) return;
        const modal = $('#standModal');
        modal.find('[data-role="title"]').text(payload.label + ' · ' + payload.product_name);
        modal.find('[data-role="description"]').text(payload.product_description || 'Aucune description.');
        modal.find('[data-role="note"]').text(payload.note || 'Sans note complémentaire.');
        modal.find('[name="stand_id"]').val(payload.id);
        modal.find('[name="event_id"]').val(payload.event_id);
        modal.find('[data-role="price"]').text(payload.price_ttc_label);
        modal.find('[data-role="occupant"]').text(payload.occupant || 'Disponible');
        const image = modal.find('[data-role="image"]');
        if (payload.product_image) {
            image.attr('src', payload.product_image).removeClass('d-none');
        } else {
            image.addClass('d-none');
        }
        const box = modal.find('[data-role="options"]').empty();
        if (payload.options.length === 0) {
            box.append('<p class="text-muted mb-0">Aucune option complémentaire.</p>');
        } else {
            payload.options.forEach(function (option) {
                const optionId = String(option.id);
                const wrapper = $('<div>').addClass('form-check border rounded-3 p-2 mb-2 bg-light-subtle');
                const input = $('<input>')
                    .addClass('form-check-input')
                    .attr({
                        type: 'checkbox',
                        name: 'option_ids[]',
                        value: optionId,
                        id: 'option-' + optionId
                    });
                const label = $('<label>')
                    .addClass('form-check-label w-100')
                    .attr('for', 'option-' + optionId);
                label.append($('<strong>').text(option.name || 'Option'));
                label.append('<br>');
                label.append($('<small>').text(option.description || ''));
                label.append('<br>');
                label.append($('<span>').addClass('badge text-bg-secondary mt-1').text(option.price_ttc_label || ''));
                wrapper.append(input, label);
                box.append(wrapper);
            });
        }
        bootstrap.Modal.getOrCreateInstance(modal[0]).show();
    });

    $('#mapClickBoard').on('click', function (event) {
        const board = $(this);
        if (!board.data('editable')) return;
        const offset = board.offset();
        const x = ((event.pageX - offset.left) / board.width()) * 100;
        const y = ((event.pageY - offset.top) / board.height()) * 100;
        $('#standEditorForm [name="x_coord"]').val(x.toFixed(2));
        $('#standEditorForm [name="y_coord"]').val(y.toFixed(2));
        $('#coordHelp').text('Coordonnées sélectionnées : ' + x.toFixed(2) + '% / ' + y.toFixed(2) + '%');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('standEditorModal')).show();
    });

    $('[data-edit-stand]').on('click', function (event) {
        event.stopPropagation();
        const payload = JSON.parse($(this).attr('data-edit-stand') || '{}');
        const form = $('#standEditorForm');
        form.find('[name="stand_id"]').val(payload.id);
        form.find('[name="label"]').val(payload.label);
        form.find('[name="product_id"]').val(payload.product_id);
        form.find('[name="x_coord"]').val(payload.x_coord);
        form.find('[name="y_coord"]').val(payload.y_coord);
        form.find('[name="note"]').val(payload.note);
        form.find('input[name="option_ids[]"]').prop('checked', false);
        (payload.option_ids || []).forEach(function (id) {
            form.find('input[name="option_ids[]"][value="' + id + '"]').prop('checked', true);
        });
        $('#coordHelp').text('Coordonnées actuelles : ' + payload.x_coord + '% / ' + payload.y_coord + '%');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('standEditorModal')).show();
    });

    const chartEl = document.getElementById('revenueChart');
    if (chartEl && chartEl.dataset.labels) {
        new Chart(chartEl, {
            type: 'bar',
            data: {
                labels: JSON.parse(chartEl.dataset.labels),
                datasets: [{
                    label: 'CA TTC',
                    data: JSON.parse(chartEl.dataset.values),
                    backgroundColor: '#7b4b2a'
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } }
            }
        });
    }
});
