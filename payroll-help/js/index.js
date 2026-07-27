(function (window, document) {
    'use strict';

    var searchInput = document.getElementById('payrollFaqSearch');
    var categoryButtons = Array.prototype.slice.call(document.querySelectorAll('.payroll-faq-category'));
    var faqItems = Array.prototype.slice.call(document.querySelectorAll('.payroll-faq-item'));
    var emptyState = document.getElementById('payrollFaqEmpty');
    var resultCount = document.getElementById('payrollFaqResultCount');
    var activeCategory = 'all';
    var processImageData = '';

    function normalize(value) {
        return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
    }

    function filterFaqs() {
        var query = normalize(searchInput.value);
        var visible = 0;
        faqItems.forEach(function (item) {
            var categoryMatch = activeCategory === 'all' || item.getAttribute('data-category') === activeCategory;
            var queryMatch = !query || normalize(item.getAttribute('data-search')).indexOf(query) !== -1;
            var show = categoryMatch && queryMatch;
            item.hidden = !show;
            if (show) {
                visible++;
            } else {
                item.open = false;
            }
        });
        emptyState.hidden = visible !== 0;
        resultCount.textContent = visible + ' of ' + faqItems.length + ' payroll questions shown.';
    }

    function selectCategory(button) {
        activeCategory = button.getAttribute('data-category') || 'all';
        categoryButtons.forEach(function (item) {
            var selected = item === button;
            item.classList.toggle('btn-primary', selected);
            item.classList.toggle('btn-outline-secondary', !selected);
            item.classList.toggle('active', selected);
            item.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
        filterFaqs();
    }

    function renderProcessImage() {
        if (!window.HrisHelp || !window.HrisHelpGuides) {
            return;
        }
        processImageData = window.HrisHelp.createProcessImage(
            window.HrisHelpGuides.payrollFlow,
            'End-to-end payroll'
        );
        var image = document.getElementById('payrollFaqProcessImage');
        image.src = processImageData;
        image.alt = 'End-to-end payroll process: prepare employee master, stage DTR, resolve identity and population, load payroll inputs atomically, generate and reconcile, then approve, post, and distribute.';
    }

    searchInput.addEventListener('input', filterFaqs);
    categoryButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            selectCategory(button);
        });
    });
    document.getElementById('payrollFaqProcessTrigger').addEventListener('click', function (event) {
        if (!processImageData) {
            renderProcessImage();
        }
        if (!processImageData || !window.HrisHelp || !window.HrisHelp.openProcessImage) {
            return;
        }
        window.HrisHelp.openProcessImage(
            processImageData,
            document.getElementById('payrollFaqProcessImage').alt,
            'End-to-end payroll process flow',
            event.currentTarget
        );
    });

    renderProcessImage();
    filterFaqs();
}(window, document));
