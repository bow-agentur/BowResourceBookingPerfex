/**
 * Planning Board - Float-like Resource Timeline
 * JavaScript for the resource planning board with drag & drop
 * 
 * Uses interact.js for drag & resize functionality
 * 
 * @since 2.0.0
 */

var PlanningBoard = (function() {
    'use strict';
    
    // Configuration
    var config = {
        baseUrl: '',
        csrfToken: '',
        canEdit: false,
        canDelete: false,
        canCreate: false,
        lang: {}
    };
    
    // State
    var state = {
        currentView: 'month', // 'week' or 'month'
        startDate: null,
        endDate: null,
        staff: [],
        allocations: [],
        timeOff: [],
        projects: [],
        capacity: {}, // capacity per staff per day
        cellWidth: 40, // pixels per day
        isLoading: false,
        filterStaff: null,
        filterProject: null
    };
    
    // DOM Elements
    var $board, $dateHeader, $datesContainer, $boardBody;
    
    /**
     * Initialize the planning board
     */
    function init(options) {
        $.extend(config, options);
        
        // Check interact.js availability
        if (typeof interact === 'undefined') {
            // interact.js not loaded - drag/drop will be disabled
        }
        
        // Set initial date range
        var today = new Date();
        if (state.currentView === 'month') {
            state.startDate = new Date(today.getFullYear(), today.getMonth(), 1);
            state.endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        } else {
            // Week view: Monday to Sunday
            var day = today.getDay();
            var diff = today.getDate() - day + (day === 0 ? -6 : 1);
            state.startDate = new Date(today.setDate(diff));
            state.endDate = new Date(state.startDate);
            state.endDate.setDate(state.endDate.getDate() + 6);
        }
        
        // Cache DOM elements
        $board = $('.rb-board-container');
        $dateHeader = $('#rb-date-header');
        $datesContainer = $('#rb-dates-container');
        $boardBody = $('#rb-board-body');
        
        // Bind events
        bindEvents();
        
        // Sync horizontal scroll between header and body
        bindScrollSync();
        
        // Load initial data
        loadBoardData();
    }
    
    /**
     * Synchronize horizontal scroll between date header and body
     */
    function bindScrollSync() {
        // When body scrolls horizontally, move the date header via transform
        $boardBody.on('scroll', function() {
            var scrollLeft = $(this).scrollLeft();
            // Use transform for smooth performance
            $datesContainer.css('transform', 'translateX(-' + scrollLeft + 'px)');
        });
    }
    
    /**
     * Bind all event handlers
     */
    function bindEvents() {
        // Navigation
        $('#rb-prev').on('click', navigatePrev);
        $('#rb-next').on('click', navigateNext);
        $('#rb-today').on('click', navigateToday);
        
        // Apply custom date range
        $('#rb-apply-dates').on('click', applyCustomDateRange);
        
        // View toggle
        $('.rb-view-btn').on('click', function() {
            var view = $(this).data('view');
            changeView(view);
        });
        
        // Filters
        $('#rb-filter-staff').on('change', function() {
            state.filterStaff = $(this).val() || null;
            renderBoard();
        });
        
        $('#rb-filter-project').on('change', function() {
            state.filterProject = $(this).val() || null;
            renderBoard();
        });
        
        // Add allocation button
        $('#rb-add-allocation').on('click', function() {
            openAllocationModal();
        });
        
        // Modal save buttons
        $('#rb-save-allocation').on('click', saveAllocation);
        $('#rb-save-timeoff').on('click', saveTimeOff);
        $('#rb-delete-allocation').on('click', deleteAllocation);
        
        // Form change handlers for live total calculation
        $('#rb-alloc-start, #rb-alloc-end, #rb-alloc-hours').on('change', updateTotalHoursDisplay);
        
        // Double-click on allocation to edit
        $boardBody.on('dblclick', '.rb-allocation', function(e) {
            e.stopPropagation();
            var id = $(this).data('id');
            openAllocationModal(id);
        });
        
        // Double-click on empty cell to create
        $boardBody.on('dblclick', '.rb-timeline-cell', function(e) {
            if (!config.canCreate) return;
            if ($(e.target).hasClass('rb-allocation')) return;
            
            var staffId = $(this).closest('.rb-staff-row').data('staff-id');
            var date = $(this).data('date');
            openAllocationModal(null, staffId, date);
        });
        
        // Initialize datepickers
        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true
        });
        
        // Update date inputs when state changes
        updateDateInputs();
    }
    
    /**
     * Update date filter inputs with current state
     */
    function updateDateInputs() {
        $('#rb-date-from').val(formatDate(state.startDate));
        $('#rb-date-to').val(formatDate(state.endDate));
    }
    
    /**
     * Apply custom date range from filter inputs
     */
    function applyCustomDateRange() {
        var fromVal = $('#rb-date-from').val();
        var toVal = $('#rb-date-to').val();
        
        if (fromVal && toVal) {
            state.startDate = new Date(fromVal);
            state.endDate = new Date(toVal);
            
            // Validate dates
            if (state.startDate > state.endDate) {
                alert_float('warning', 'Start date must be before end date');
                return;
            }
            
            loadBoardData();
        } else {
            alert_float('warning', 'Please select both start and end dates');
        }
    }
    
    /**
     * Load board data from API
     */
    function loadBoardData() {
        if (state.isLoading) return;
        
        state.isLoading = true;
        showLoading(true);
        
        var params = {
            start_date: formatDate(state.startDate),
            end_date: formatDate(state.endDate)
        };
        
        $.ajax({
            url: config.apiUrl + '/api_board_data',
            type: 'GET',
            data: params,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    state.staff = response.data.staff || [];
                    state.allocations = response.data.allocations || [];
                    state.timeOff = response.data.time_off || [];
                    state.projects = response.data.projects || [];
                    state.capacity = response.data.capacity || {};
                    
                    renderBoard();
                    checkOverbooking(); // Check and display overbooking warnings
                } else {
                    alert_float('danger', response.message || config.lang.errorLoading);
                }
            },
            error: function() {
                alert_float('danger', config.lang.errorLoading);
            },
            complete: function() {
                state.isLoading = false;
                showLoading(false);
            }
        });
    }
    
    /**
     * Render the complete board
     */
    function renderBoard() {
        renderDateHeader();
        renderStaffRows();
        syncBoardWidth();
        initDragDrop();
    }
    
    /**
     * Synchronize width between date header and staff rows
     */
    function syncBoardWidth() {
        // Calculate total width based on number of dates and cell width
        var dates = getDateRange(state.startDate, state.endDate, true);
        var totalWidth = dates.length * state.cellWidth;
        
        // Set min-width on dates container
        $datesContainer.css('min-width', totalWidth + 'px');
        
        // Set min-width on each timeline grid
        $boardBody.find('.rb-timeline-grid').css('min-width', totalWidth + 'px');
    }
    
    /**
     * Render date header cells (including weekends)
     */
    function renderDateHeader() {
        var html = '';
        var dates = getDateRange(state.startDate, state.endDate, true); // Include weekends
        var dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        var today = formatDate(new Date());
        
        dates.forEach(function(date) {
            var d = new Date(date);
            var isWeekend = d.getDay() === 0 || d.getDay() === 6;
            var isToday = date === today;
            
            var classes = 'rb-date-cell';
            if (isWeekend) classes += ' weekend';
            if (isToday) classes += ' today';
            
            html += '<div class="' + classes + '" data-date="' + date + '">';
            html += '<span class="rb-day-name">' + dayNames[d.getDay()] + '</span>';
            html += '<span class="rb-day-num">' + d.getDate() + '</span>';
            html += '</div>';
        });
        
        $datesContainer.html(html);
    }
    
    /**
     * Render staff rows with allocations
     */
    function renderStaffRows() {
        var html = '';
        var filteredStaff = state.staff;
        
        // Apply staff filter
        if (state.filterStaff) {
            filteredStaff = state.staff.filter(function(s) {
                return s.staffid == state.filterStaff;
            });
        }
        
        if (filteredStaff.length === 0) {
            html = '<div class="rb-empty-state">';
            html += '<i class="fa fa-users"></i>';
            html += '<p>' + config.lang.noAllocations + '</p>';
            html += '</div>';
            $boardBody.html(html);
            return;
        }
        
        filteredStaff.forEach(function(staff) {
            html += renderStaffRow(staff);
        });
        
        $boardBody.html(html);
    }
    
    /**
     * Render single staff row
     */
    function renderStaffRow(staff) {
        var initials = getInitials(staff.firstname, staff.lastname);
        var dates = getDateRange(state.startDate, state.endDate, true); // Include weekends
        var today = formatDate(new Date());
        
        // Get allocations for this staff
        var staffAllocations = state.allocations.filter(function(a) {
            return a.staff_id == staff.staffid;
        });
        
        // Apply project filter
        if (state.filterProject) {
            staffAllocations = staffAllocations.filter(function(a) {
                return a.project_id == state.filterProject;
            });
        }
        
        // Calculate total allocated hours in visible date range
        var totalAllocatedHours = calculateStaffTotalHours(staffAllocations, dates);
        
        // Get time off for this staff
        var staffTimeOff = state.timeOff.filter(function(t) {
            return t.staff_id == staff.staffid;
        });
        
        var html = '<div class="rb-staff-row" data-staff-id="' + staff.staffid + '">';
        
        // Staff column
        html += '<div class="rb-staff-column">';
        
        // Avatar with profile image or initials
        if (staff.profile_image) {
            html += '<div class="rb-staff-avatar rb-has-image">';
            html += '<img src="' + config.baseUrl + 'uploads/staff_profile_images/' + staff.staffid + '/thumb_' + staff.profile_image + '" alt="' + staff.firstname + '">';
            html += '</div>';
        } else {
            html += '<div class="rb-staff-avatar" style="background-color:' + (staff.color || '#3498db') + '">' + initials + '</div>';
        }
        
        // Calculate overall staff status for the visible period
        var staffStatus = calculateStaffStatus(staff.staffid, totalAllocatedHours, staff.weekly_hours || 40);
        
        html += '<div class="rb-staff-info">';
        html += '<div class="rb-staff-name">';
        html += '<span class="rb-status-dot ' + staffStatus.class + '" title="' + staffStatus.title + '"></span>';
        html += staff.firstname + ' ' + staff.lastname;
        html += '</div>';
        html += '<div class="rb-staff-capacity">' + (staff.weekly_hours || 40) + 'h/week</div>';
        html += '<div class="rb-staff-allocated">' + totalAllocatedHours + 'h ' + config.lang.allocated + '</div>';
        html += '</div>';
        html += '</div>';
        
        // Timeline grid (including weekends)
        html += '<div class="rb-timeline-grid">';
        
        dates.forEach(function(date) {
            var d = new Date(date);
            var isWeekend = d.getDay() === 0 || d.getDay() === 6;
            var isToday = date === today;
            
            // Check capacity status for this day
            var capacityStatus = getCapacityStatus(staff.staffid, date);
            
            var classes = 'rb-timeline-cell';
            if (isWeekend) classes += ' weekend';
            if (isToday) classes += ' today';
            if (!isWeekend) {
                if (capacityStatus === 'overbooked') classes += ' rb-overbooked';
                else if (capacityStatus === 'warning') classes += ' rb-warning';
                else if (capacityStatus === 'full') classes += ' rb-full';
            }
            
            html += '<div class="' + classes + '" data-date="' + date + '"></div>';
        });
        
        // Render allocations (split at weekends)
        staffAllocations.forEach(function(alloc) {
            html += renderAllocationSegments(alloc, dates);
        });
        
        // Render time off
        staffTimeOff.forEach(function(to) {
            html += renderTimeOffSegments(to, dates);
        });
        
        html += '</div>'; // .rb-timeline-grid
        html += '</div>'; // .rb-staff-row
        
        return html;
    }
    
    /**
     * Render allocation as segments (split at weekends unless include_weekends is true)
     * Creates separate visual bars for each consecutive working day period
     */
    function renderAllocationSegments(alloc, dates) {
        var html = '';
        var color = alloc.project_color || '#3498db';
        var textColor = isLightColor(color) ? '#000' : '#fff';
        var isOverbooked = checkAllocationOverbooking(alloc);
        var includeWeekends = alloc.include_weekends == 1 || alloc.include_weekends === true;
        
        // Calculate total hours for this allocation
        var workingDays = getDateRange(alloc.start_date, alloc.end_date, includeWeekends);
        var hoursPerDay = parseFloat(alloc.hours_per_day) || 0;
        var totalHours = workingDays.length * hoursPerDay;
        
        // Get all dates in the allocation range
        var allocDates = getDateRange(alloc.start_date, alloc.end_date, true);
        
        // Find segments of consecutive days (respect include_weekends setting)
        var segments = [];
        var currentSegment = null;
        
        allocDates.forEach(function(dateStr) {
            var d = new Date(dateStr);
            var isWeekend = d.getDay() === 0 || d.getDay() === 6;
            var isVisible = dates.indexOf(dateStr) !== -1;
            
            // Include this day if: (not weekend) OR (weekend AND include_weekends is true)
            var includeDay = (!isWeekend || includeWeekends) && isVisible;
            
            if (includeDay) {
                if (!currentSegment) {
                    currentSegment = { start: dateStr, end: dateStr };
                } else {
                    currentSegment.end = dateStr;
                }
            } else {
                // Weekend (without include_weekends) or not visible - close current segment
                if (currentSegment) {
                    segments.push(currentSegment);
                    currentSegment = null;
                }
            }
        });
        
        // Don't forget the last segment
        if (currentSegment) {
            segments.push(currentSegment);
        }
        
        // Render each segment
        segments.forEach(function(segment, index) {
            var startIndex = dates.indexOf(segment.start);
            var endIndex = dates.indexOf(segment.end);
            
            if (startIndex === -1 || endIndex === -1) return;
            
            var left = startIndex * state.cellWidth;
            var width = (endIndex - startIndex + 1) * state.cellWidth - 4;
            
            var allocClasses = 'rb-allocation';
            if (isOverbooked) allocClasses += ' rb-allocation-overbooked';
            if (index === 0) allocClasses += ' rb-segment-first';
            if (index === segments.length - 1) allocClasses += ' rb-segment-last';
            if (segments.length > 1 && index > 0 && index < segments.length - 1) allocClasses += ' rb-segment-middle';
            
            html += '<div class="' + allocClasses + '" ';
            html += 'data-id="' + alloc.id + '" ';
            html += 'data-staff-id="' + alloc.staff_id + '" ';
            html += 'data-start="' + alloc.start_date + '" ';
            html += 'data-end="' + alloc.end_date + '" ';
            html += 'data-segment="' + index + '" ';
            html += 'style="left:' + left + 'px;width:' + width + 'px;';
            html += 'background-color:' + color + ';color:' + textColor + '">';
            
            // Show content only on first segment (or if single segment)
            if (index === 0) {
                if (isOverbooked) {
                    html += '<i class="fa fa-exclamation-triangle rb-overbooking-icon" title="Overbooking!"></i>';
                }
                html += '<div class="rb-allocation-title">' + (alloc.project_name || 'Internal') + '</div>';
                html += '<div class="rb-allocation-hours">' + alloc.hours_per_day + 'h/day</div>';
                html += '<div class="rb-allocation-total">' + totalHours + 'h total</div>';
            }
            
            // Resize handles only on first and last segments
            if (config.canEdit) {
                if (index === 0) {
                    html += '<div class="rb-resize-handle rb-resize-left"></div>';
                }
                if (index === segments.length - 1) {
                    html += '<div class="rb-resize-handle rb-resize-right"></div>';
                }
            }
            
            html += '</div>';
        });
        
        return html;
    }
    
    /**
     * Render time off as segments (split at weekends)
     */
    function renderTimeOffSegments(timeOff, dates) {
        var html = '';
        
        // Get all dates in the time off range
        var offDates = getDateRange(timeOff.start_date, timeOff.end_date, true);
        
        // Find segments of consecutive working days
        var segments = [];
        var currentSegment = null;
        
        offDates.forEach(function(dateStr) {
            var d = new Date(dateStr);
            var isWeekend = d.getDay() === 0 || d.getDay() === 6;
            var isVisible = dates.indexOf(dateStr) !== -1;
            
            if (!isWeekend && isVisible) {
                if (!currentSegment) {
                    currentSegment = { start: dateStr, end: dateStr };
                } else {
                    currentSegment.end = dateStr;
                }
            } else {
                if (currentSegment) {
                    segments.push(currentSegment);
                    currentSegment = null;
                }
            }
        });
        
        if (currentSegment) {
            segments.push(currentSegment);
        }
        
        // Render each segment
        segments.forEach(function(segment, index) {
            var startIndex = dates.indexOf(segment.start);
            var endIndex = dates.indexOf(segment.end);
            
            if (startIndex === -1 || endIndex === -1) return;
            
            var left = startIndex * state.cellWidth;
            var width = (endIndex - startIndex + 1) * state.cellWidth - 4;
            
            html += '<div class="rb-allocation rb-time-off ' + timeOff.type + '" ';
            html += 'data-timeoff-id="' + timeOff.id + '" ';
            html += 'style="left:' + left + 'px;width:' + width + 'px">';
            
            if (index === 0) {
                html += '<div class="rb-allocation-title">' + capitalizeFirst(timeOff.type) + '</div>';
            }
            
            html += '</div>';
        });
        
        return html;
    }
    
    /**
     * Initialize interact.js drag and drop
     */
    function initDragDrop() {
        if (!config.canEdit) {
            return;
        }
        
        // Check if interact.js is loaded
        if (typeof interact === 'undefined') {
            return;
        }
        
        // Unset any existing interactions first
        interact('.rb-allocation:not(.rb-time-off)').unset();
        
        // Make allocations draggable and resizable
        interact('.rb-allocation:not(.rb-time-off)')
            .draggable({
                inertia: false,
                autoScroll: true,
                modifiers: [
                    interact.modifiers.restrictRect({
                        restriction: '.rb-board-body',
                        endOnly: true
                    })
                ],
                listeners: {
                    start: function(event) {
                        $(event.target).addClass('rb-dragging');
                    },
                    move: function(event) {
                        var target = event.target;
                        var x = (parseFloat(target.getAttribute('data-x')) || 0) + event.dx;
                        var y = (parseFloat(target.getAttribute('data-y')) || 0) + event.dy;
                        
                        target.style.transform = 'translate(' + x + 'px, ' + y + 'px)';
                        target.setAttribute('data-x', x);
                        target.setAttribute('data-y', y);
                    },
                    end: function(event) {
                        var $target = $(event.target);
                        $target.removeClass('rb-dragging');
                        
                        var x = parseFloat(event.target.getAttribute('data-x')) || 0;
                        var y = parseFloat(event.target.getAttribute('data-y')) || 0;
                        
                        // Calculate new date and staff
                        var daysMoved = Math.round(x / state.cellWidth);
                        var rowsMoved = Math.round(y / 60); // Approximate row height
                        
                        if (daysMoved !== 0 || rowsMoved !== 0) {
                            var id = $target.data('id');
                            moveAllocation(id, daysMoved, rowsMoved);
                        } else {
                            // Reset position
                            event.target.style.transform = '';
                            event.target.removeAttribute('data-x');
                            event.target.removeAttribute('data-y');
                        }
                    }
                }
            })
            .resizable({
                edges: { 
                    left: '.rb-resize-left', 
                    right: '.rb-resize-right',
                    top: false,
                    bottom: false
                },
                listeners: {
                    start: function(event) {
                        // Resize started
                    },
                    move: function(event) {
                        var target = event.target;
                        var x = parseFloat(target.getAttribute('data-x')) || 0;
                        
                        // Update width
                        target.style.width = event.rect.width + 'px';
                        
                        // Translate when resizing from left edge
                        x += event.deltaRect.left;
                        target.style.transform = 'translateX(' + x + 'px)';
                        target.setAttribute('data-x', x);
                    },
                    end: function(event) {
                        var $target = $(event.target);
                        var id = $target.data('id');
                        
                        // Calculate new dates based on position and width changes
                        var x = parseFloat(event.target.getAttribute('data-x')) || 0;
                        var originalLeft = parseFloat($target.css('left')) || 0;
                        var newWidth = event.rect.width;
                        
                        // Days moved from start
                        var startDaysDelta = Math.round(x / state.cellWidth);
                        // New duration in days
                        var newDurationDays = Math.round(newWidth / state.cellWidth);
                        
                        resizeAllocation(id, startDaysDelta, newDurationDays);
                    }
                }
            });
    }
    
    /**
     * Move allocation via API
     */
    function moveAllocation(id, daysDelta, staffDelta) {
        var alloc = state.allocations.find(function(a) { return a.id == id; });
        if (!alloc) return;
        
        // Calculate new dates
        var newStart = addDays(new Date(alloc.start_date), daysDelta);
        var newEnd = addDays(new Date(alloc.end_date), daysDelta);
        
        // Calculate new staff (if moved vertically)
        var newStaffId = alloc.staff_id;
        if (staffDelta !== 0) {
            var currentIndex = state.staff.findIndex(function(s) { return s.staffid == alloc.staff_id; });
            var newIndex = currentIndex + staffDelta;
            if (newIndex >= 0 && newIndex < state.staff.length) {
                newStaffId = state.staff[newIndex].staffid;
            }
        }
        
        $.ajax({
            url: config.apiUrl + '/api_allocation_move/' + id,
            type: 'POST',
            data: {
                csrf_token_name: config.csrfToken,
                start_date: formatDate(newStart),
                end_date: formatDate(newEnd),
                staff_id: newStaffId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (response.warning) {
                        alert_float('warning', response.warning);
                    } else {
                        alert_float('success', config.lang.allocationMoved || 'Moved');
                    }
                    loadBoardData(); // Reload to get updated positions
                } else {
                    alert_float('danger', response.message);
                    loadBoardData(); // Reset
                }
            },
            error: function() {
                alert_float('danger', config.lang.errorSaving);
                loadBoardData();
            }
        });
    }
    
    /**
     * Resize allocation via API
     * @param {number} id - Allocation ID
     * @param {number} startDaysDelta - Days to shift start date (negative = earlier, positive = later)
     * @param {number} newDurationDays - New total duration in days
     */
    function resizeAllocation(id, startDaysDelta, newDurationDays) {
        var alloc = state.allocations.find(function(a) { return a.id == id; });
        if (!alloc) {
            loadBoardData();
            return;
        }
        
        // Calculate new dates
        var originalStart = new Date(alloc.start_date);
        var newStart = addDays(originalStart, startDaysDelta);
        var newEnd = addDays(newStart, newDurationDays - 1); // -1 because duration includes start day
        
        $.ajax({
            url: config.apiUrl + '/api_allocation/' + id,
            type: 'POST', // Use POST with _method for better compatibility
            data: {
                _method: 'PUT',
                start_date: formatDate(newStart),
                end_date: formatDate(newEnd)
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert_float('success', 'Updated');
                    loadBoardData();
                } else {
                    alert_float('danger', response.message || 'Error updating');
                    loadBoardData();
                }
            },
            error: function(xhr) {
                alert_float('danger', config.lang.errorSaving);
                loadBoardData();
            }
        });
    }
    
    /**
     * Open allocation modal for create/edit
     */
    function openAllocationModal(id, staffId, date) {
        var $modal = $('#rb-allocation-modal');
        var $form = $('#rb-allocation-form');
        var $title = $('#rb-modal-title');
        var $deleteBtn = $('#rb-delete-allocation');
        
        $form[0].reset();
        $('#rb-overbooking-warning').hide();
        $('#rb-alloc-weekends').prop('checked', false); // Default: no weekends
        
        if (id) {
            // Edit mode
            var alloc = state.allocations.find(function(a) { return a.id == id; });
            if (!alloc) return;
            
            $title.text('Edit Allocation');
            $('#rb-alloc-id').val(alloc.id);
            $('#rb-alloc-staff').selectpicker('val', alloc.staff_id);
            $('#rb-alloc-project').selectpicker('val', alloc.project_id || '');
            $('#rb-alloc-start').val(alloc.start_date);
            $('#rb-alloc-end').val(alloc.end_date);
            $('#rb-alloc-hours').val(alloc.hours_per_day);
            $('#rb-alloc-weekends').prop('checked', alloc.include_weekends == 1 || alloc.include_weekends === '1');
            $('#rb-alloc-note').val(alloc.note || '');
            
            $deleteBtn.show();
        } else {
            // Create mode
            $title.text('New Allocation');
            $('#rb-alloc-id').val('');
            $deleteBtn.hide();
            
            if (staffId) {
                $('#rb-alloc-staff').selectpicker('val', staffId);
            }
            if (date) {
                $('#rb-alloc-start').val(date);
                $('#rb-alloc-end').val(date);
            }
        }
        
        $('.selectpicker').selectpicker('refresh');
        updateTotalHoursDisplay();
        $modal.modal('show');
    }
    
    /**
     * Save allocation (create or update)
     */
    function saveAllocation() {
        var $form = $('#rb-allocation-form');
        var id = $('#rb-alloc-id').val();
        
        var data = {
            staff_id: $('#rb-alloc-staff').val(),
            project_id: $('#rb-alloc-project').val() || null,
            start_date: $('#rb-alloc-start').val(),
            end_date: $('#rb-alloc-end').val(),
            hours_per_day: $('#rb-alloc-hours').val(),
            include_weekends: $('#rb-alloc-weekends').is(':checked') ? 1 : 0,
            note: $('#rb-alloc-note').val()
        };
        
        // Validation
        if (!data.staff_id) {
            alert_float('warning', 'Please select a staff member');
            return;
        }
        if (!data.start_date || !data.end_date) {
            alert_float('warning', 'Please select dates');
            return;
        }
        
        var url = config.apiUrl + '/api_allocations';
        var method = 'POST';
        
        if (id) {
            url = config.apiUrl + '/api_allocation/' + id;
            // Use POST with _method override for better compatibility
            data._method = 'PUT';
        }
        
        $.ajax({
            url: url,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#rb-allocation-modal').modal('hide');
                    if (response.warning) {
                        alert_float('warning', response.warning);
                    } else {
                        alert_float('success', id ? 'Updated' : 'Created');
                    }
                    loadBoardData();
                } else {
                    alert_float('danger', response.message || config.lang.errorSaving);
                }
            },
            error: function() {
                alert_float('danger', config.lang.errorSaving);
            }
        });
    }
    
    /**
     * Delete allocation
     */
    function deleteAllocation() {
        var id = $('#rb-alloc-id').val();
        if (!id) return;
        
        if (!confirm(config.lang.confirmDelete)) return;
        
        $.ajax({
            url: config.apiUrl + '/api_allocation/' + id,
            type: 'DELETE',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#rb-allocation-modal').modal('hide');
                    alert_float('success', 'Deleted');
                    loadBoardData();
                } else {
                    alert_float('danger', response.message);
                }
            },
            error: function() {
                alert_float('danger', 'Error deleting');
            }
        });
    }
    
    /**
     * Save time off request
     */
    function saveTimeOff() {
        var data = {
            staff_id: $('#rb-timeoff-staff').val(),
            type: $('#rb-timeoff-type').val(),
            start_date: $('#rb-timeoff-start').val(),
            end_date: $('#rb-timeoff-end').val(),
            note: $('#rb-timeoff-note').val()
        };
        
        $.ajax({
            url: config.apiUrl + '/api_time_off',
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#rb-timeoff-modal').modal('hide');
                    alert_float('success', 'Time off requested');
                    loadBoardData();
                } else {
                    alert_float('danger', response.message);
                }
            },
            error: function() {
                alert_float('danger', 'Error saving');
            }
        });
    }
    
    /**
     * Update total hours display in modal
     */
    function updateTotalHoursDisplay() {
        var start = $('#rb-alloc-start').val();
        var end = $('#rb-alloc-end').val();
        var hoursPerDay = parseFloat($('#rb-alloc-hours').val()) || 0;
        
        if (start && end && hoursPerDay > 0) {
            var days = countWorkingDays(new Date(start), new Date(end));
            var total = days * hoursPerDay;
            $('#rb-total-hours-display').text(days + ' working days × ' + hoursPerDay + 'h = ' + total + ' hours total');
        } else {
            $('#rb-total-hours-display').text('');
        }
    }
    
    /**
     * Navigation: Previous period
     */
    function navigatePrev() {
        if (state.currentView === 'month') {
            state.startDate.setMonth(state.startDate.getMonth() - 1);
            state.endDate = new Date(state.startDate.getFullYear(), state.startDate.getMonth() + 1, 0);
        } else {
            state.startDate.setDate(state.startDate.getDate() - 7);
            state.endDate.setDate(state.endDate.getDate() - 7);
        }
        updateDateInputs();
        loadBoardData();
    }
    
    /**
     * Navigation: Next period
     */
    function navigateNext() {
        if (state.currentView === 'month') {
            state.startDate.setMonth(state.startDate.getMonth() + 1);
            state.endDate = new Date(state.startDate.getFullYear(), state.startDate.getMonth() + 1, 0);
        } else {
            state.startDate.setDate(state.startDate.getDate() + 7);
            state.endDate.setDate(state.endDate.getDate() + 7);
        }
        updateDateInputs();
        loadBoardData();
    }
    
    /**
     * Navigation: Today
     */
    function navigateToday() {
        var today = new Date();
        if (state.currentView === 'month') {
            state.startDate = new Date(today.getFullYear(), today.getMonth(), 1);
            state.endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        } else {
            var day = today.getDay();
            var diff = today.getDate() - day + (day === 0 ? -6 : 1);
            state.startDate = new Date(today.setDate(diff));
            state.endDate = new Date(state.startDate);
            state.endDate.setDate(state.endDate.getDate() + 6);
        }
        updateDateInputs();
        loadBoardData();
    }
    
    /**
     * Change view mode
     */
    function changeView(view) {
        if (view === state.currentView) return;
        
        state.currentView = view;
        $('.rb-view-btn').removeClass('active');
        $('.rb-view-btn[data-view="' + view + '"]').addClass('active');
        
        // Adjust date range
        var today = new Date();
        if (view === 'month') {
            state.startDate = new Date(today.getFullYear(), today.getMonth(), 1);
            state.endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        } else {
            var day = today.getDay();
            var diff = today.getDate() - day + (day === 0 ? -6 : 1);
            state.startDate = new Date(today);
            state.startDate.setDate(diff);
            state.endDate = new Date(state.startDate);
            state.endDate.setDate(state.endDate.getDate() + 6);
        }
        
        loadBoardData();
    }
    
    /**
     * Show/hide loading indicator
     */
    function showLoading(show) {
        if (show) {
            $('#rb-loading').removeClass('hidden');
        } else {
            $('#rb-loading').addClass('hidden');
        }
    }
    
    // ===== UTILITY FUNCTIONS =====
    
    function formatDate(date) {
        var d = new Date(date);
        var month = '' + (d.getMonth() + 1);
        var day = '' + d.getDate();
        var year = d.getFullYear();
        
        if (month.length < 2) month = '0' + month;
        if (day.length < 2) day = '0' + day;
        
        return [year, month, day].join('-');
    }
    
    function getDateRange(start, end, includeWeekends) {
        var dates = [];
        var current = new Date(start);
        var endDate = new Date(end);
        
        while (current <= endDate) {
            var day = current.getDay();
            // Skip weekends unless explicitly included
            if (includeWeekends || (day !== 0 && day !== 6)) {
                dates.push(formatDate(current));
            }
            current.setDate(current.getDate() + 1);
        }
        
        return dates;
    }
    
    function addDays(date, days) {
        var result = new Date(date);
        result.setDate(result.getDate() + days);
        return result;
    }
    
    function countWorkingDays(start, end) {
        var count = 0;
        var current = new Date(start);
        
        while (current <= end) {
            var day = current.getDay();
            if (day !== 0 && day !== 6) count++;
            current.setDate(current.getDate() + 1);
        }
        
        return count;
    }
    
    /**
     * Calculate total allocated hours for a staff member in visible date range
     */
    function calculateStaffTotalHours(allocations, visibleDates) {
        var totalHours = 0;
        
        allocations.forEach(function(alloc) {
            var allocDates = getDateRange(alloc.start_date, alloc.end_date, false); // Working days only
            var hoursPerDay = parseFloat(alloc.hours_per_day) || 0;
            
            // Count only days that are in the visible range
            allocDates.forEach(function(date) {
                if (visibleDates.indexOf(date) !== -1) {
                    totalHours += hoursPerDay;
                }
            });
        });
        
        return Math.round(totalHours * 10) / 10; // Round to 1 decimal
    }
    
    /**
     * Calculate staff status based on workload for the visible period
     * Returns object with class and title for the status dot
     */
    function calculateStaffStatus(staffId, allocatedHours, weeklyCapacity) {
        // Count visible working days
        var dates = getDateRange(state.startDate, state.endDate, false);
        var workingDays = dates.filter(function(date) {
            var d = new Date(date);
            return d.getDay() !== 0 && d.getDay() !== 6;
        }).length;
        
        // Calculate total capacity for visible period
        var weeksInPeriod = workingDays / 5;
        var totalCapacity = weeksInPeriod * weeklyCapacity;
        
        if (totalCapacity === 0) {
            return { class: 'rb-status-neutral', title: 'No working days in period' };
        }
        
        var utilization = (allocatedHours / totalCapacity) * 100;
        
        if (utilization > 100) {
            return { class: 'rb-status-overbooked', title: 'Overbooked (' + Math.round(utilization) + '%)' };
        } else if (utilization >= 80) {
            return { class: 'rb-status-busy', title: 'High workload (' + Math.round(utilization) + '%)' };
        } else if (utilization >= 50) {
            return { class: 'rb-status-moderate', title: 'Moderate workload (' + Math.round(utilization) + '%)' };
        } else if (utilization > 0) {
            return { class: 'rb-status-available', title: 'Available (' + Math.round(utilization) + '%)' };
        } else {
            return { class: 'rb-status-free', title: 'No allocations' };
        }
    }
    
    function getInitials(firstname, lastname) {
        var initials = '';
        if (firstname) initials += firstname.charAt(0).toUpperCase();
        if (lastname) initials += lastname.charAt(0).toUpperCase();
        return initials || '??';
    }
    
    function isLightColor(hex) {
        hex = hex.replace('#', '');
        if (hex.length === 3) {
            hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
        }
        var r = parseInt(hex.substr(0, 2), 16);
        var g = parseInt(hex.substr(2, 2), 16);
        var b = parseInt(hex.substr(4, 2), 16);
        var brightness = (r * 299 + g * 587 + b * 114) / 1000;
        return brightness > 128;
    }
    
    function capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    /**
     * Get capacity status for a staff member on a specific date
     * Returns: 'ok', 'warning', 'full', 'overbooked', or 'off'
     */
    function getCapacityStatus(staffId, date) {
        // Ensure staffId is a string for consistent lookup
        var staffKey = String(staffId);
        
        // Check if we have capacity data for this staff
        if (!state.capacity || !state.capacity[staffKey]) {
            return 'ok';
        }
        
        var staffCapacity = state.capacity[staffKey];
        if (!staffCapacity[date]) {
            return 'ok';
        }
        
        return staffCapacity[date].status || 'ok';
    }
    
    /**
     * Check if an allocation contributes to overbooking on any day
     */
    function checkAllocationOverbooking(alloc) {
        var staffId = String(alloc.staff_id);
        var start = new Date(alloc.start_date);
        var end = new Date(alloc.end_date);
        var current = new Date(start);
        
        while (current <= end) {
            var dateStr = formatDate(current);
            var status = getCapacityStatus(staffId, dateStr);
            if (status === 'overbooked') {
                return true;
            }
            current.setDate(current.getDate() + 1);
        }
        
        return false;
    }
    
    /**
     * Check for overbooking and display warnings
     */
    function checkOverbooking() {
        var overbookedStaff = [];
        
        state.staff.forEach(function(staff) {
            var staffKey = String(staff.staffid);
            var staffCapacity = state.capacity[staffKey];
            if (!staffCapacity) {
                return;
            }
            
            var hasOverbooking = false;
            var overbookedDates = [];
            
            Object.keys(staffCapacity).forEach(function(date) {
                if (staffCapacity[date].status === 'overbooked') {
                    hasOverbooking = true;
                    overbookedDates.push(date);
                }
            });
            
            if (hasOverbooking) {
                overbookedStaff.push({
                    name: staff.firstname + ' ' + staff.lastname,
                    dates: overbookedDates
                });
            }
        });
        
        // Display warning if there are overbookings
        var $warning = $('#rb-overbooking-warning');
        if (overbookedStaff.length > 0) {
            var message = overbookedStaff.map(function(s) {
                return s.name + ' (' + s.dates.length + ' day' + (s.dates.length > 1 ? 's' : '') + ')';
            }).join(', ');
            
            $('#rb-overbooking-message').text(message);
            $warning.slideDown();
        } else {
            $warning.slideUp();
        }
    }
    
    // Public API
    return {
        init: init,
        reload: loadBoardData
    };
    
})();
