// This file is part of Moodle - https://moodle.org/

/**
 * Sort users in the group overview by firstname or lastname.
 *
 * @module     mod_grouptool/sortoverview
 * @copyright  2026 Academic Moodle Cooperation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Update the displayed sort icons.
 *
 * @param {HTMLTableElement} table
 * @param {HTMLButtonElement} activeButton
 * @param {String} direction
 */
const updateSortIcons = (table, activeButton, direction) => {
    table.querySelectorAll('.grouptool-sort-name').forEach(button => {
        const defaultIcon = button.querySelector('.grouptool-sort-default');
        const ascendingIcon = button.querySelector('.grouptool-sort-ascending');
        const descendingIcon = button.querySelector('.grouptool-sort-descending');

        defaultIcon?.classList.remove('d-none');
        ascendingIcon?.classList.add('d-none');
        descendingIcon?.classList.add('d-none');

        button.removeAttribute('aria-sort');
    });

    const defaultIcon = activeButton.querySelector('.grouptool-sort-default');
    const ascendingIcon = activeButton.querySelector('.grouptool-sort-ascending');
    const descendingIcon = activeButton.querySelector('.grouptool-sort-descending');

    defaultIcon?.classList.add('d-none');

    if (direction === 'asc') {
        ascendingIcon?.classList.remove('d-none');
        activeButton.setAttribute('aria-sort', 'ascending');
    } else {
        descendingIcon?.classList.remove('d-none');
        activeButton.setAttribute('aria-sort', 'descending');
    }
};

/**
 * Sort one group table.
 *
 * @param {HTMLButtonElement} button
 */
const sortTable = button => {
    const table = button.closest('.grouptool-user-table');

    if (!table) {
        return;
    }

    const tbody = table.querySelector('tbody');
    const field = button.dataset.sortField;

    if (!tbody || !field) {
        return;
    }

    const previousField = table.dataset.sortField;
    const previousDirection = table.dataset.sortDirection;

    let direction = 'asc';

    if (previousField === field && previousDirection === 'asc') {
        direction = 'desc';
    }

    table.dataset.sortField = field;
    table.dataset.sortDirection = direction;

    const sortableRows = Array.from(
        tbody.querySelectorAll('tr.grouptool-sortable-user')
    );

    const staticRows = Array.from(
        tbody.querySelectorAll('tr.grouptool-static-row')
    );

    sortableRows.sort((firstRow, secondRow) => {
        const firstValue = firstRow.dataset[field] ?? '';
        const secondValue = secondRow.dataset[field] ?? '';

        const comparison = firstValue.localeCompare(
            secondValue,
            document.documentElement.lang || undefined,
            {
                sensitivity: 'base',
                numeric: true,
                ignorePunctuation: true
            }
        );

        return direction === 'asc' ? comparison : -comparison;
    });

    /*
     * Rebuild the tbody:
     * 1. sorted user rows
     * 2. static messages such as "Nobody queued"
     */
    tbody.replaceChildren(...sortableRows, ...staticRows);

    updateSortIcons(table, button, direction);
};

/**
 * Initialise table sorting.
 */
export const init = () => {
    document.addEventListener('click', event => {
        const button = event.target.closest('.grouptool-sort-name');

        if (!button) {
            return;
        }

        sortTable(button);
    });
};