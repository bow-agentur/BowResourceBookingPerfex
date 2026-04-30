/**
 * PlanningBoard Utils Module
 * Pure utility functions — no DOM, no jQuery.
 *
 * @since 2.0.0
 */
var PB_Utils = (function () {
    'use strict';

    /**
     * Parse a Date object or 'YYYY-MM-DD' string as LOCAL midnight.
     * Native new Date('YYYY-MM-DD') parses as UTC midnight, causing a day
     * shift in positive-offset timezones (e.g. UTC+2 → previous day).
     */
    function _parseDateSafe(d) {
        if (d instanceof Date) return new Date(d);
        var s = String(d);
        if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
            var p = s.split('-');
            return new Date(+p[0], +p[1] - 1, +p[2]);
        }
        return new Date(s);
    }

    /**
     * Format a Date object or parseable string as 'YYYY-MM-DD'.
     */
    function formatDate(date) {
        var d   = _parseDateSafe(date);
        var m   = '' + (d.getMonth() + 1);
        var day = '' + d.getDate();
        if (m.length < 2)   m   = '0' + m;
        if (day.length < 2) day = '0' + day;
        return d.getFullYear() + '-' + m + '-' + day;
    }

    /**
     * Return array of 'YYYY-MM-DD' strings from start to end (inclusive).
     * @param {Date|string} start
     * @param {Date|string} end
     * @param {boolean}     includeWeekends
     */
    function getDateRange(start, end, includeWeekends) {
        var dates = [];
        var cur   = _parseDateSafe(start);
        var endD  = _parseDateSafe(end);
        while (cur <= endD) {
            var dow = cur.getDay();
            if (includeWeekends || (dow !== 0 && dow !== 6)) {
                dates.push(formatDate(cur));
            }
            cur.setDate(cur.getDate() + 1);
        }
        return dates;
    }

    /** Return a new Date offset by |days| days. */
    function addDays(date, days) {
        var r = new Date(date);
        r.setDate(r.getDate() + days);
        return r;
    }

    /** Count Mon–Fri days between start and end (inclusive). */
    function countWorkingDays(start, end) {
        var n = 0;
        var c = _parseDateSafe(start);
        var e = _parseDateSafe(end);
        while (c <= e) {
            var d = c.getDay();
            if (d !== 0 && d !== 6) n++;
            c.setDate(c.getDate() + 1);
        }
        return n;
    }

    /** Build two-letter initials from firstname + lastname. */
    function getInitials(fn, ln) {
        return ((fn ? fn.charAt(0).toUpperCase() : '') +
                (ln ? ln.charAt(0).toUpperCase() : '')) || '??';
    }

    /**
     * Returns true when the hex color is perceptually "light" (use dark text on it).
     */
    function isLightColor(hex) {
        hex = (hex || '#3498db').replace('#', '');
        if (hex.length === 3) {
            hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
        }
        var r = parseInt(hex.substr(0, 2), 16);
        var g = parseInt(hex.substr(2, 2), 16);
        var b = parseInt(hex.substr(4, 2), 16);
        return (r * 299 + g * 587 + b * 114) / 1000 > 128;
    }

    function capitalizeFirst(s) {
        return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
    }

    /** Escape a string for safe insertion into HTML attributes and text nodes. */
    function escHtml(s) {
        return String(s || '')
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#039;');
    }

    /**
     * Calculate hours-per-working-day average.
     * @param  {number}  estimatedHours
     * @param  {string}  dateFrom     YYYY-MM-DD
     * @param  {string}  dateTo       YYYY-MM-DD
     * @param  {boolean} includeWeekends
     * @return {number|null}
     */
    function calcDailyAvg(estimatedHours, dateFrom, dateTo, includeWeekends) {
        if (!estimatedHours || !dateFrom || !dateTo) return null;
        var days = includeWeekends
            ? Math.round((new Date(dateTo) - new Date(dateFrom)) / 86400000) + 1
            : countWorkingDays(new Date(dateFrom), new Date(dateTo));
        return days > 0 ? Math.round((estimatedHours / days) * 10) / 10 : null;
    }

    return {
        formatDate:       formatDate,
        getDateRange:     getDateRange,
        addDays:          addDays,
        countWorkingDays: countWorkingDays,
        getInitials:      getInitials,
        isLightColor:     isLightColor,
        capitalizeFirst:  capitalizeFirst,
        escHtml:          escHtml,
        calcDailyAvg:     calcDailyAvg
    };

})();
