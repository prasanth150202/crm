/**
 * Meetings Module
 * Handles meeting management and display
 */

export const Meetings = {
    currentPage: 1,
    perPage: 20,

    /**
     * Initialize meetings module
     */
    async init() {
        try {
            console.log('Meetings module initializing...');
            await this.loadMeetings();
            console.log('Meetings module initialized successfully');
        } catch (error) {
            console.error('Meetings init failed:', error);
            App.showToast('Error loading meetings view', 'error');
        }
    },

    /**
     * Load meetings list
     */
    async loadMeetings(leadId = null) {
        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                per_page: this.perPage
            });

            if (leadId) {
                params.append('lead_id', leadId);
            }

            const apiUrl = App.apiUrl || '/api';
            console.log(`Fetching meetings from: ${apiUrl}/meetings/list.php`, params.toString());

            const response = await fetch(`${apiUrl}/meetings/list.php?${params}`, {
                credentials: 'include'
            });
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

            const data = await response.json();

            if (data.success) {
                if (leadId) {
                    return data.meetings;
                } else {
                    this.renderMeetingsList(data.meetings, data.pagination);
                }
            } else {
                throw new Error(data.error || 'Failed to load meetings');
            }
        } catch (error) {
            console.error('Error loading meetings:', error);
            App.showToast('Failed to load meetings: ' + error.message, 'error');
        }
    },

    /**
     * Render meetings list view
     */
    renderMeetingsList(meetings, pagination) {
        const container = document.getElementById('appContent');

        let html = `
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-900">All Meetings</h2>
                    <button onclick="Meetings.openCreateModal()" 
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                        <i data-lucide="plus" class="h-4 w-4 mr-2"></i>
                        Schedule Meeting
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lead</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mode</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
        `;

        if (meetings.length === 0) {
            html += `
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <i data-lucide="calendar" class="h-12 w-12 mx-auto mb-4 text-gray-400"></i>
                        <p class="text-lg font-medium">No meetings scheduled</p>
                        <p class="text-sm mt-2">Schedule your first meeting to get started</p>
                    </td>
                </tr>
            `;
        } else {
            meetings.forEach(meeting => {
                const meetingDate = new Date(meeting.meeting_date);
                const formattedDate = meetingDate.toLocaleDateString();
                const formattedTime = meetingDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                html += `
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">${this.escapeHtml(meeting.title)}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${this.escapeHtml(meeting.lead_name || 'N/A')}</div>
                            <div class="text-xs text-gray-500">${this.escapeHtml(meeting.lead_email || '')}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${formattedDate}</div>
                            <div class="text-xs text-gray-500">${formattedTime}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            ${meeting.duration} min
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${this.getModeClass(meeting.mode)}">
                                ${this.formatMode(meeting.mode)}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            ${this.escapeHtml(meeting.created_by_name || 'Unknown')}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="Meetings.viewMeeting(${meeting.id})" class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                            <button onclick="Meetings.editMeeting(${meeting.id})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                            <button onclick="Meetings.deleteMeeting(${meeting.id})" class="text-red-600 hover:text-red-900">Delete</button>
                        </td>
                    </tr>
                `;
            });
        }

        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        container.innerHTML = html;

        // Reinitialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    },

    /**
     * Open create meeting modal
     */
    async openCreateModal(leadId = null, leadName = null) {
        console.log('Opening Create Meeting Modal', { leadId });
        const modalId = 'createMeetingModal';

        // Open immediately so user sees something is happening
        App.openModal(modalId);

        const modal = document.getElementById(modalId);
        const form = document.getElementById('createMeetingForm');

        if (!modal || !form) {
            console.error('Modal or form not found!', { modalId, formId: 'createMeetingForm' });
            App.showToast('UI Error: Meeting modal not found', 'error');
            return;
        }

        const title = document.getElementById('meetingModalTitle');
        const leadSelect = document.getElementById('meetingLeadSelect');
        const leadNameInput = document.getElementById('meetingLeadName');

        // Reset form
        form.reset();
        document.getElementById('meetingId').value = '';
        document.getElementById('meetingLeadId').value = leadId || '';
        if (title) title.textContent = 'Schedule Meeting';

        // Handle lead selection
        if (leadId) {
            console.log('Lead ID provided for modal:', leadId);
            if (leadSelect) leadSelect.classList.add('hidden');
            if (leadNameInput) {
                leadNameInput.classList.remove('hidden');
                leadNameInput.value = leadName || 'Current Lead';
            }
        } else {
            console.log('No Lead ID, populating dropdown...');
            if (leadSelect) leadSelect.classList.remove('hidden');
            if (leadNameInput) leadNameInput.classList.add('hidden');
            await this.populateLeadSelect();
        }

        // Set default date/time
        const now = new Date();
        const dateInput = document.getElementById('meetingDate');
        const timeInput = document.getElementById('meetingTime');
        if (dateInput) dateInput.valueAsDate = now;
        if (timeInput) {
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            timeInput.value = `${hours}:${minutes}`;
        }

        // Reset disabled states and submit button
        const inputs = form.querySelectorAll('input, select, textarea');
        const submitBtn = form.querySelector('button[type="submit"]');
        inputs.forEach(input => input.disabled = false);
        if (submitBtn) submitBtn.style.display = 'block';

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        console.log('Meeting Modal initialization complete');
    },

    /**
     * Close create meeting modal
     */
    closeCreateModal() {
        App.closeModal('createMeetingModal');
    },

    /**
     * Populate leads dropdown
     */
    async populateLeadSelect() {
        const select = document.getElementById('meetingLeadSelect');
        if (!select) return;

        select.innerHTML = '<option value="">Loading...</option>';

        try {
            const user = App.requireAuth();
            if (!user || !user.org_id) {
                throw new Error('User context missing or not authenticated');
            }

            const apiUrl = App.apiUrl || '/api';
            console.log(`Populating leads from: ${apiUrl}/leads/list.php?org_id=${user.org_id}`);

            const response = await fetch(`${apiUrl}/leads/list.php?org_id=${user.org_id}&limit=100`, {
                credentials: 'include'
            });
            const data = await response.json();

            if (data && data.data) {
                let options = '<option value="">Select a Lead...</option>';
                data.data.forEach(lead => {
                    options += `<option value="${lead.id}">${this.escapeHtml(lead.name)} (${this.escapeHtml(lead.company || 'No Company')})</option>`;
                });
                select.innerHTML = options;
                console.log('Leads dropdown populated successfully');
            } else {
                console.warn('No leads data found in response', data);
                select.innerHTML = '<option value="">No leads found</option>';
            }
        } catch (error) {
            console.error('Error fetching leads for modal:', error);
            select.innerHTML = '<option value="">Error loading leads</option>';
            App.showToast('Could not load leads for selection', 'warning');
        }
    },

    /**
     * Save meeting (Create/Update)
     */
    async saveMeeting(event) {
        event.preventDefault();

        const form = event.target;
        const formData = new FormData(form);
        const meetingId = formData.get('id');
        const isEdit = !!meetingId;

        // Handle lead_id logic
        let leadId = formData.get('lead_id');
        if (!leadId) {
            leadId = formData.get('lead_select');
        }

        if (!leadId) {
            App.showToast('Please select a lead', 'error');
            return;
        }

        const data = {
            id: meetingId,
            lead_id: leadId,
            title: formData.get('title'),
            meeting_date: formData.get('meeting_date') + ' ' + formData.get('meeting_time') + ':00',
            duration: formData.get('duration'),
            mode: formData.get('mode'),
            description: formData.get('description')
        };

        try {
            const apiUrl = App.apiUrl || '/api';
            const endpoint = isEdit ? '/meetings/update.php' : '/meetings/create.php';

            const response = await fetch(`${apiUrl}${endpoint}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                App.showToast(`Meeting ${isEdit ? 'updated' : 'scheduled'} successfully`, 'success');
                App.closeModal('createMeetingModal');
                await this.loadMeetings();
            } else {
                throw new Error(result.error || 'Failed to save meeting');
            }
        } catch (error) {
            console.error('Error saving meeting:', error);
            App.showToast(error.message, 'error');
        }
    },

    /**
     * View meeting details
     */
    viewMeeting(meetingId) {
        // For now, reuse edit modal but we could add a read-only flag
        this.editMeeting(meetingId, true);
    },

    /**
     * Edit meeting
     */
    async editMeeting(meetingId, readOnly = false) {
        try {
            const apiUrl = App.apiUrl || '/api';
            const response = await fetch(`${apiUrl}/meetings/list.php?id=${meetingId}`, {
                credentials: 'include'
            });
            const data = await response.json();

            if (!data.success || !data.meetings || data.meetings.length === 0) {
                throw new Error('Meeting not found');
            }

            const meeting = data.meetings[0];

            // Open modal
            await this.openCreateModal(meeting.lead_id, meeting.lead_name);

            // Populate form
            document.getElementById('meetingModalTitle').textContent = readOnly ? 'View Meeting' : 'Edit Meeting';
            document.getElementById('meetingId').value = meeting.id;
            document.getElementById('meetingTitle').value = meeting.title;

            // Handle date/time split (meeting_date is YYYY-MM-DD HH:MM:SS)
            const dt = new Date(meeting.meeting_date);
            document.getElementById('meetingDate').value = dt.toISOString().split('T')[0];

            const hours = String(dt.getHours()).padStart(2, '0');
            const minutes = String(dt.getMinutes()).padStart(2, '0');
            document.getElementById('meetingTime').value = `${hours}:${minutes}`;

            document.getElementById('meetingDuration').value = meeting.duration;
            document.getElementById('meetingMode').value = meeting.mode;
            document.getElementById('meetingDescription').value = meeting.notes || '';

            // Handle lead selection UI
            const leadNameInput = document.getElementById('meetingLeadName');
            const leadSelect = document.getElementById('meetingLeadSelect');

            if (leadNameInput) {
                leadNameInput.value = meeting.lead_name || 'N/A';
                leadNameInput.classList.remove('hidden');
            }
            if (leadSelect) {
                leadSelect.classList.add('hidden');
            }

            // Disable fields if readOnly
            const form = document.getElementById('createMeetingForm');
            const inputs = form.querySelectorAll('input, select, textarea');
            const submitBtn = form.querySelector('button[type="submit"]');

            inputs.forEach(input => {
                if (input.type !== 'hidden') {
                    input.disabled = readOnly;
                }
            });

            if (submitBtn) {
                submitBtn.style.display = readOnly ? 'none' : 'block';
            }

        } catch (error) {
            console.error('Error fetching meeting details:', error);
            App.showToast('Could not load meeting details', 'error');
        }
    },

    /**
     * Delete meeting
     */
    async deleteMeeting(meetingId) {
        if (!confirm('Are you sure you want to delete this meeting?')) {
            return;
        }

        try {
            const apiUrl = App.apiUrl || '/api';
            const response = await fetch(`${apiUrl}/meetings/delete.php?id=${meetingId}`, {
                method: 'DELETE',
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success) {
                App.showToast('Meeting deleted successfully', 'success');
                await this.loadMeetings();
            } else {
                throw new Error(data.error || 'Failed to delete meeting');
            }
        } catch (error) {
            console.error('Error deleting meeting:', error);
            App.showToast('Failed to delete meeting', 'error');
        }
    },

    /**
     * Helper: Get CSS class for meeting mode
     */
    getModeClass(mode) {
        const classes = {
            'in_person': 'bg-green-100 text-green-800',
            'phone': 'bg-blue-100 text-blue-800',
            'video': 'bg-purple-100 text-purple-800',
            'other': 'bg-gray-100 text-gray-800'
        };
        return classes[mode] || classes.other;
    },

    /**
     * Helper: Format meeting mode for display
     */
    formatMode(mode) {
        const labels = {
            'in_person': 'In Person',
            'phone': 'Phone',
            'video': 'Video',
            'other': 'Other'
        };
        return labels[mode] || mode;
    },

    /**
     * Helper: Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Make globally available
window.Meetings = Meetings;
