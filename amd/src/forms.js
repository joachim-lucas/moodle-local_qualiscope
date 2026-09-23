define([], function() {
    'use strict';

    return {
        init: function() {
            document.querySelectorAll('[data-toggle="qualiscope-evidence-form"]').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var form = document.getElementById('evidence-form');
                    if (form) {
                        form.style.display = form.style.display === 'none' ? 'block' : 'none';
                    }
                });
            });

            document.querySelectorAll('[data-toggle="qualiscope-action-form"]').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var form = document.getElementById('action-form');
                    if (form) {
                        form.style.display = form.style.display === 'none' ? 'block' : 'none';
                    }
                });
            });

            document.querySelectorAll('[data-toggle="qualiscope-campaign-form"]').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var form = document.getElementById('campaign-form');
                    if (form) {
                        form.style.display = form.style.display === 'none' ? 'block' : 'none';
                    }
                });
            });

            var scopeSelect = document.getElementById('scope-select');
            if (scopeSelect) {
                scopeSelect.addEventListener('change', function() {
                    var cats = document.getElementById('scope-categories');
                    var courses = document.getElementById('scope-courses');
                    if (cats) cats.style.display = this.value === 'category' ? 'block' : 'none';
                    if (courses) courses.style.display = this.value === 'selected' ? 'block' : 'none';
                });
            }

            // Campaign course filtering and instant search.
            var searchInput = document.getElementById('courseFilterSearch');
            var scoreSelect = document.getElementById('courseFilterCoverage');
            var statusSelect = document.getElementById('courseFilterStatus');
            var countBadge = document.getElementById('courseFilterCount');
            var noResultsMsg = document.getElementById('courseFilterNoResults');
            var courseRows = document.querySelectorAll('.qualiscope-course-row');

            /**
             * Applies the search, score and status filters to the campaign course table.
             *
             * @return {void}
             */
            function applyCourseFilters() {
                if (!courseRows.length) {
                    return;
                }

                var query = searchInput ? searchInput.value.toLowerCase().trim() : '';
                var scoreFilter = scoreSelect ? scoreSelect.value : 'all';
                var statusFilter = statusSelect ? statusSelect.value : 'all';

                var visibleCount = 0;

                courseRows.forEach(function(row) {
                    var name = (row.dataset.coursename || '').toLowerCase();
                    var pct = parseInt(row.dataset.percentage, 10) || 0;
                    var missing = parseInt(row.dataset.missing, 10) || 0;
                    var verify = parseInt(row.dataset.verify, 10) || 0;

                    var matchesQuery = !query || name.indexOf(query) !== -1;

                    var matchesScore = true;
                    if (scoreFilter === 'gte75') {
                        matchesScore = pct >= 75;
                    } else if (scoreFilter === '50to75') {
                        matchesScore = pct >= 50 && pct < 75;
                    } else if (scoreFilter === 'lt50') {
                        matchesScore = pct < 50;
                    }

                    var matchesStatus = true;
                    if (statusFilter === 'missing') {
                        matchesStatus = missing > 0;
                    } else if (statusFilter === 'verify') {
                        matchesStatus = verify > 0;
                    } else if (statusFilter === 'perfect') {
                        matchesStatus = missing === 0 && verify === 0;
                    }

                    if (matchesQuery && matchesScore && matchesStatus) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                if (countBadge) {
                    countBadge.textContent = visibleCount;
                }
                if (noResultsMsg) {
                    noResultsMsg.style.display = visibleCount === 0 ? '' : 'none';
                }
            }

            if (searchInput) {
                searchInput.addEventListener('input', applyCourseFilters);
            }
            if (scoreSelect) {
                scoreSelect.addEventListener('change', applyCourseFilters);
            }
            if (statusSelect) {
                statusSelect.addEventListener('change', applyCourseFilters);
            }
        }
    };
});
