// Load jsPDF and jsPDF-AutoTable before this helper in admin pages.
(function (window) {
    'use strict';

    function cleanText(value, maxLength) {
        const text = String(value == null ? '' : value)
            .replace(/[\u0000-\u001f\u007f]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
        const limit = maxLength === undefined ? 240 : maxLength;
        return limit !== null && text.length > limit ? `${text.slice(0, limit - 3)}...` : text;
    }

    function filterLines(meta) {
        const filters = [];
        if (meta.statusFilter) filters.push(`Status: ${cleanText(meta.statusFilter)}`);
        if (meta.searchQuery) filters.push(`Search: ${cleanText(meta.searchQuery)}`);
        if (meta.categoryFilter) filters.push(`Category: ${cleanText(meta.categoryFilter)}`);
        if (meta.accountFilter) filters.push(`Account: ${cleanText(meta.accountFilter)}`);
        if (meta.urgencyFilter) filters.push(`Urgency: ${cleanText(meta.urgencyFilter)}`);
        if (meta.dateRange) filters.push(`Date range: ${cleanText(meta.dateRange)}`);
        return filters;
    }

    async function exportTable(options) {
        if (!window.jspdf || !window.jspdf.jsPDF) {
            throw new Error('PDF library is unavailable. Please reload the page and try again.');
        }
        if (typeof window.jspdf.jsPDF.API.autoTable !== 'function') {
            throw new Error('PDF table support is unavailable. Please reload the page and try again.');
        }

        await new Promise(resolve => window.setTimeout(resolve, 0));

        const { jsPDF } = window.jspdf;
        const columns = options.columns || [];
        const rows = options.rows || [];
        const meta = options.meta || {};
        const orientation = options.orientation || (columns.length > 6 ? 'landscape' : 'portrait');
        const doc = new jsPDF({ orientation, unit: 'mm', format: 'a4' });
        const pageWidth = doc.internal.pageSize.getWidth();
        const filters = filterLines(meta);
        const generatedAt = new Date();
        const generatedBy = cleanText(meta.generatedBy || '');
        const headerHeight = 43 + (filters.length ? 6 : 0);

        doc.autoTable({
            columns: columns.map(column => ({ header: cleanText(column.header), dataKey: column.dataKey })),
            body: rows.map(row => {
                const cleanRow = {};
                columns.forEach(column => {
                    cleanRow[column.dataKey] = cleanText(row[column.dataKey], column.preserveFullText ? null : undefined);
                });
                return cleanRow;
            }),
            startY: headerHeight + 4,
            margin: { top: headerHeight + 4, right: 12, bottom: 17, left: 12 },
            styles: { font: 'helvetica', fontSize: 8, cellPadding: 2.2, overflow: 'linebreak', textColor: [45, 55, 50] },
            headStyles: { fillColor: [34, 139, 87], textColor: [255, 255, 255], fontStyle: 'bold' },
            alternateRowStyles: { fillColor: [245, 249, 246] },
            didDrawPage: function () {
                const pageHeight = doc.internal.pageSize.getHeight();
                doc.setFillColor(34, 139, 87);
                doc.rect(0, 0, pageWidth, 8, 'F');
                doc.setTextColor(255, 255, 255);
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(10);
                doc.text('SKalinga  |  Barangay San Antonio  |  SK', 12, 5.5);

                doc.setTextColor(35, 48, 40);
                doc.setFontSize(15);
                doc.text(cleanText(options.title || 'Administrative Report'), 12, 17);
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(9);
                if (options.subtitle) doc.text(cleanText(options.subtitle), 12, 23);
                doc.setFontSize(8);
                doc.text(`Generated: ${generatedAt.toLocaleString()}`, 12, 29);
                if (generatedBy) doc.text(`Exported by: ${generatedBy}`, 12, 34);
                if (filters.length) doc.text(filters.join('  |  '), 12, 39 + (generatedBy ? 0 : -5));

                doc.setDrawColor(200, 213, 204);
                doc.line(12, pageHeight - 12, pageWidth - 12, pageHeight - 12);
                doc.setFontSize(8);
                doc.setTextColor(90, 100, 94);
                doc.text('Confidential – for SK official use', 12, pageHeight - 7);
                doc.text(`Page ${doc.internal.getNumberOfPages()}`, pageWidth - 12, pageHeight - 7, { align: 'right' });
            }
        });

        doc.save(options.filename || 'skalinga-report.pdf');
    }

    function dateStamp(date) {
        const value = date || new Date();
        return [value.getFullYear(), String(value.getMonth() + 1).padStart(2, '0'), String(value.getDate()).padStart(2, '0')].join('-');
    }

    window.AdminExportPDF = { exportTable, dateStamp };
})(window);
