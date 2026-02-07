<?php
// admin/events.php

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/_header.php';


<div class="container-fluid">
    <div class="row">
        <div class="col">
            <h1 class="h3 mb-4 text-gray-800">Event Management</h1>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary mb-4" data-toggle="modal" data-target="#createEventModal">
                <i class="fas fa-plus"></i> Create New Event
            </button>
        </div>
    </div>

    <!-- Event Listing Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">All Events</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="eventsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Venue</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Capacity</th>
                            <th>Tickets Sold</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Event rows will be loaded here via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modals for Create/Edit Event -->
<?php require_once __DIR__ . '/partials/events/_event_modals.php'; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const API_BASE_URL = '/api/v1'; // Adjust if your API base URL is different

        // Function to fetch and display events
        function fetchEvents() {
            fetch(`${API_BASE_URL}/events`)
                .then(response => response.json())
                .then(data => {
                    const tableBody = document.querySelector('#eventsTable tbody');
                    tableBody.innerHTML = ''; // Clear existing rows
                    if (data.success && data.data.length > 0) {
                        data.data.forEach(event => {
                            const row = `
                                <tr>
                                    <td>${event.title}</td>
                                    <td>${event.venue_name || 'N/A'} (${event.city})</td>
                                    <td>${new Date(event.starts_at).toLocaleString()}</td>
                                    <td>${event.status}</td>
                                    <td>${event.total_capacity || 'Unlimited'}</td>
                                    <td>${event.tickets_sold || 0}</td>
                                    <td>
                                        <button class="btn btn-info btn-sm edit-event-btn" data-id="${event.id}">Edit</button>
                                        <button class="btn btn-danger btn-sm delete-event-btn" data-id="${event.id}">Delete</button>
                                        <button class="btn btn-primary btn-sm manage-lineup-btn" data-id="${event.id}">Lineup</button>
                                        ${event.status === 'draft' ? `<button class="btn btn-success btn-sm publish-event-btn" data-id="${event.id}">Publish</button>` : ''}
                                        ${event.status !== 'cancelled' ? `<button class="btn btn-warning btn-sm cancel-event-btn" data-id="${event.id}">Cancel</button>` : ''}
                                    </td>
                                </tr>
                            `;
                            tableBody.insertAdjacentHTML('beforeend', row);
                        });
                    } else {
                        tableBody.innerHTML = '<tr><td colspan="7">No events found.</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching events:', error);
                    document.querySelector('#eventsTable tbody').innerHTML = '<tr><td colspan="7" class="text-danger">Failed to load events.</td></tr>';
                });
        }

        // Initial fetch
        fetchEvents();

        // Event listeners for actions (to be implemented in partials/_event_modals.php or similar)
        // For example, using Bootstrap modals for forms.
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('edit-event-btn')) {
                const eventId = e.target.dataset.id;
                // Populate edit modal and show
                // $('#editEventModal').modal('show');
            }
            if (e.target.classList.contains('delete-event-btn')) {
                const eventId = e.target.dataset.id;
                if (confirm('Are you sure you want to delete this event?')) {
                    fetch(`${API_BASE_URL}/events/${eventId}`, { method: 'DELETE' })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert(data.message);
                                fetchEvents();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => console.error('Error deleting event:', error));
                }
            }
            // Add similar logic for publish, cancel, manage lineup buttons
        });

        // Form submission for create/edit (example for create)
        // document.getElementById('createEventForm').addEventListener('submit', function(e) {
        //     e.preventDefault();
        //     const formData = new FormData(this);
        //     const eventData = Object.fromEntries(formData.entries());
        //
        //     fetch(`${API_BASE_URL}/events`, {
        //         method: 'POST',
        //         headers: {
        //             'Content-Type': 'application/json',
        //             'Authorization': 'Bearer YOUR_ADMIN_TOKEN_HERE' // IMPORTANT: Replace with actual token
        //         },
        //         body: JSON.stringify(eventData)
        //     })
        //     .then(response => response.json())
        //     .then(data => {
        //         if (data.success) {
        //             alert('Event created successfully!');
        //             $('#createEventModal').modal('hide');
        //             fetchEvents();
        //         } else {
        //             alert('Error creating event: ' + data.message);
        //         }
        //     })
        //     .catch(error => console.error('Error creating event:', error));
        // });
    });
</script>