// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

import repository from './repository';
import {exception as displayException} from 'core/notification';
import {get_strings as getStrings} from 'core/str';
import {addIconToContainerWithPromise} from 'core/loadingicon';

const selectors = {
    searchForm: '#bigbluebuttonbn_recordings_searchform',
    table: '#bigbluebuttonbn_recordings_table',
};

    // eslint-disable-next-line
const convertFeaturesToMap = profileFeatures => {
    const mappedFeatures = new Map();
    for (const feature of profileFeatures) {
        mappedFeatures.set(feature, true);
    }

    return mappedFeatures;
};

/**
 * Initiate the YUI langauge strings with appropriate values for the sortable list from Moodle.
 *
 * @param   {YUI} Y
 * @returns {Promise}
 */
const initYuiLanguage = Y => {
    const stringList = [
        'view_recording_yui_first',
        'view_recording_yui_prev',
        'view_recording_yui_next',
        'view_recording_yui_last',
        'view_recording_yui_page',
        'view_recording_yui_go',
        'view_recording_yui_rows',
        'view_recording_yui_show_all',
    ].map(key => {
        return {
            key,
            component: 'bigbluebuttonbn',
        };
    });

    return getStrings(stringList)
    .then(([first, prev, next, last, goToLabel, goToAction, perPage, showAll]) => {
        Y.Intl.add('datatable-paginator', Y.config.lang, {
            first,
            prev,
            next,
            last,
            goToLabel,
            goToAction,
            perPage,
            showAll,
        });

        return;
    })
    .catch();
};

/**
 * Format the supplied date per the specified locale.
 *
 * @param   {string} locale
 * @param   {array} dateList
 * @returns {array}
 */
const formatDates = (locale, dateList) => dateList.map(row => {
    const date = new Date(row.date);
    row.date = date.toLocaleDateString(locale, {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });

    return row;
});

/**
 * Format response data for the table.
 *
 * @param   {string} response JSON-encoded table data
 * @returns {array}
 */
const getFormattedData = response => {
    const recordingData = response.tabledata;
    let rowData = JSON.parse(recordingData.data);

    rowData = formatDates(recordingData.locale, rowData);

    return rowData;
};

const getDataTableFunctions = (bbbid, dataTable) => {
    const updateTableFromResponse = response => {
        if (!response || !response.status) {
            // There was no output at all.
            return;
        }

        dataTable.get('data').reset(getFormattedData(response));
        dataTable.set(
            'currentData',
            dataTable.get('data')
        );

        const currentFilter = dataTable.get('currentFilter');
        if (currentFilter) {
            filterByText(currentFilter);
        }

        return;
    };

    const refreshTableData = bbbid => repository.fetchRecordings(bbbid).then(updateTableFromResponse);

    const filterByText = value => {
        const dataModel = dataTable.get('currentData');
        dataTable.set('currentFilter', value);

        const escapedRegex = value.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, "\\$&");
        const rsearch = new RegExp(`<span>.*?${escapedRegex}.*?</span>`, 'i');

        dataTable.set('data', dataModel.filter({asList: true}, item => {
            const name = item.get('recording');
            if (name && rsearch.test(name)) {
                return true;
            }

            const description = item.get('description');
            if (description && rsearch.test(description)) {
                return true;
            }

            return false;
        }));
    };

    const requestAction = (element) => {
        const getDataFromAction = (element, dataType) => element.closest(`[data-${dataType}]`).dataset[dataType];

        const elementData = element.dataset;
        const payload = {
            bigbluebuttonbnid: bbbid,
            recordingid: getDataFromAction(element, 'recordingid'),
            action: elementData.action,
        };

        if (element.dataset.requireConfirmation == "1") {
            // Create the confirmation dialogue.
            const confirm = new M.core.confirm({
                modal: true,
                centered: true,
                question: 'Are you sure?', // Old: this.recordingConfirmationMessage(payload).
            });

            // If it is confirmed.
            return new Promise(function(resolve) {
                confirm.on('complete-yes', function() {
                    resolve(repository.updateRecording(payload));
                }, this);
                confirm.on('complete-no', function() {
                    resolve();
                }, this);
            });
        } else {
            return repository.updateRecording(payload);
        }
    };

    /**
     * Process an action event.
     *
     * @param   {Event} e
     */
    const processAction = e => {
        const popoutLink = e.target.closest('a[data-href]');
        if (popoutLink) {
            e.preventDefault();

            const videoPlayer = window.open('', '_blank');
            videoPlayer.opener = null;
            videoPlayer.location.href = popoutLink.dataset.href;

            // TODO repository.viewRecording(args); .

            return;
        }

        // Fetch any clicked anchor.
        const clickedLink = e.target.closest('a[data-action]');
        if (clickedLink) {
            e.preventDefault();

            // Create a spinning icon on the table.
            const iconPromise = addIconToContainerWithPromise(dataTable.get('boundingBox').getDOMNode());

            requestAction(clickedLink)
            .then(updateTableFromResponse)
            .catch(displayException)

            // Always resolve the iconPromise.
            .then(iconPromise.resolve)
            .catch();

            return;
        }
    };

    const processSearchSubmission = e => {
        // Prevent the default action.
        e.preventDefault();

        filterByText(e.target.elements.searchtext.value);
    };

    const processSearchReset = () => {
        filterByText('');
    };

    const registerEventListeners = () => {
        // Add event listeners to the table boundingBox.
        const boundingBox = dataTable.get('boundingBox').getDOMNode();
        boundingBox.addEventListener('click', processAction);

        // Setup the search from handlers.
        const searchForm = document.querySelector(selectors.searchForm);
        if (searchForm) {
            searchForm.addEventListener('submit', processSearchSubmission);
            searchForm.addEventListener('reset', processSearchReset);
        }
    };

    return {
        filterByText,
        refreshTableData,
        registerEventListeners,
    };
};

/**
 * Setup the data table for the specified BBB instance.
 *
 * @param   {number} bbbid The instance of the BBB CM.
 * @param   {object} response The response from the data request
 * @returns {Promise}
 */
const setupDatatable = (bbbid, response) => {
    if (!response) {
        return Promise.resolve();
    }

    if (!response.status) {
        // Something failed. Continue to show the plain output.
        return Promise.resolve();
    }

    const recordingData = response.tabledata;

    if (recordingData.recordings_html) {
        // Use the plain HTML view.
        // Note: In the future this option will be removed when the reliance upon the YUI DataTable is removed.
        return Promise.resolve();
    }

    let showRecordings = recordingData.profile_features.indexOf('all') !== -1;
    showRecordings = showRecordings || recordingData.profile_features.indexOf('showrecordings') !== -1;
    if (!showRecordings) {
        // TODO: This should be handled by the web service.
        // This user is not allowed to view recordings.
        return Promise.resolve();
    }

    return new Promise(function(resolve) {
        // eslint-disable-next-line
        YUI({
            lang: recordingData.locale,
        }).use('intl', 'datatable', 'datatable-sort', 'datatable-paginator', 'datatype-number', Y => {
            initYuiLanguage(Y)
            .then(() => {
                const tableData = getFormattedData(response);

                const dataTable = new Y.DataTable({
                    width: "1195px",
                    columns: recordingData.columns,
                    data: tableData,
                    rowsPerPage: 3,
                    paginatorLocation: ['header', 'footer']
                });
                dataTable.set('currentData', dataTable.get('data'));
                dataTable.set('currentFilter', '');

                return dataTable;
            })
            .then(resolve)
            .catch();
        });
    })
    .then(dataTable => {
        dataTable.render(selectors.table);
        const {registerEventListeners} = getDataTableFunctions(bbbid, dataTable);
        registerEventListeners();

        return dataTable;
    });
};

/**
 * Initialise recordings code.
 *
 * @method init
 * @param {object} params
 * @param {Number} params.bbbid The BBB instanceid.
 */
export const init = ({
    bbbid,
} = {}) => {
    repository.fetchRecordings(bbbid)
    .then(response => setupDatatable(bbbid, response))
    .catch(displayException);
};
