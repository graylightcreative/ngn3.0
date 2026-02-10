<?php
// admin/partials/events/_event_modals.php
?>

<!-- Create Event Modal -->
<div class="modal fade" id="createEventModal" tabindex="-1" role="dialog" aria-labelledby="createEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createEventModalLabel">Create New Event</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="createEventForm">
                    <div class="form-group">
                        <label for="eventTitle">Event Title</label>
                        <input type="text" class="form-control" id="eventTitle" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="eventDescription">Description</label>
                        <textarea class="form-control" id="eventDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="eventStartsAt">Starts At</label>
                            <input type="datetime-local" class="form-control" id="eventStartsAt" name="starts_at" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="eventEndsAt">Ends At</label>
                            <input type="datetime-local" class="form-control" id="eventEndsAt" name="ends_at">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="eventDoorsAt">Doors Open At</label>
                            <input type="datetime-local" class="form-control" id="eventDoorsAt" name="doors_at">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="eventTimezone">Timezone</label>
                            <input type="text" class="form-control" id="eventTimezone" name="timezone" value="America/New_York">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="eventVenueName">Venue Name</label>
                        <input type="text" class="form-control" id="eventVenueName" name="venue_name">
                        <small class="form-text text-muted">Automatically populated if venue_id is selected.</small>
                    </div>
                    <div class="form-group">
                        <label for="eventAddress">Address</label>
                        <input type="text" class="form-control" id="eventAddress" name="address">
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="eventCity">City</label>
                            <input type="text" class="form-control" id="eventCity" name="city">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="eventRegion">Region/State</label>
                            <input type="text" class="form-control" id="eventRegion" name="region">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="eventPostalCode">Postal Code</label>
                            <input type="text" class="form-control" id="eventPostalCode" name="postal_code">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="eventCountry">Country</label>
                            <input type="text" class="form-control" id="eventCountry" name="country" value="US">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="eventTotalCapacity">Total Capacity</label>
                            <input type="number" class="form-control" id="eventTotalCapacity" name="total_capacity">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="enableTicketing" name="enable_ticketing" value="1">
                            <label class="form-check-label" for="enableTicketing">Enable Ticketing</label>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="eventTicketSalesStartAt">Ticket Sales Start</label>
                            <input type="datetime-local" class="form-control" id="eventTicketSalesStartAt" name="ticket_sales_start_at">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="eventTicketSalesEndAt">Ticket Sales End</label>
                            <input type="datetime-local" class="form-control" id="eventTicketSalesEndAt" name="ticket_sales_end_at">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="eventBasePrice">Base Price (Cents)</label>
                            <input type="number" class="form-control" id="eventBasePrice" name="base_price_cents" value="0">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="eventNgnAllocation">NGN Allocation</label>
                            <input type="number" class="form-control" id="eventNgnAllocation" name="ngn_allocation">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="eventStatus">Status</label>
                        <select class="form-control" id="eventStatus" name="status">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="eventImageUrl">Image URL</label>
                        <input type="url" class="form-control" id="eventImageUrl" name="image_url">
                    </div>
                    <div class="form-group">
                        <label for="eventBannerUrl">Banner URL</label>
                        <input type="url" class="form-control" id="eventBannerUrl" name="banner_url">
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="isAllAges" name="is_all_ages" value="1">
                            <label class="form-check-label" for="isAllAges">All Ages Event</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="ageRestriction">Age Restriction</label>
                        <input type="text" class="form-control" id="ageRestriction" name="age_restriction" placeholder="e.g., 21+, 18+">
                    </div>
                    <div class="form-group">
                        <label for="eventMetadata">Metadata (JSON)</label>
                        <textarea class="form-control" id="eventMetadata" name="metadata" rows="3">{}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Event</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Event Modal (similar structure, populate with existing data) -->
<div class="modal fade" id="editEventModal" tabindex="-1" role="dialog" aria-labelledby="editEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editEventModalLabel">Edit Event</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editEventForm">
                    <input type="hidden" id="editEventId" name="id">
                    <div class="form-group">
                        <label for="editEventTitle">Event Title</label>
                        <input type="text" class="form-control" id="editEventTitle" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="editEventDescription">Description</label>
                        <textarea class="form-control" id="editEventDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="editEventStartsAt">Starts At</label>
                            <input type="datetime-local" class="form-control" id="editEventStartsAt" name="starts_at" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="editEventEndsAt">Ends At</label>
                            <input type="datetime-local" class="form-control" id="editEventEndsAt" name="ends_at">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="editEventDoorsAt">Doors Open At</label>
                            <input type="datetime-local" class="form-control" id="editEventDoorsAt" name="doors_at">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="editEventTimezone">Timezone</label>
                            <input type="text" class="form-control" id="editEventTimezone" name="timezone" value="America/New_York">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="editEventVenueName">Venue Name</label>
                        <input type="text" class="form-control" id="editEventVenueName" name="venue_name">
                    </div>
                    <div class="form-group">
                        <label for="editEventAddress">Address</label>
                        <input type="text" class="form-control" id="editEventAddress" name="address">
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="editEventCity">City</label>
                            <input type="text" class="form-control" id="editEventCity" name="city">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editEventRegion">Region/State</label>
                            <input type="text" class="form-control" id="editEventRegion" name="region">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editEventPostalCode">Postal Code</label>
                            <input type="text" class="form-control" id="editEventPostalCode" name="postal_code">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="editEventCountry">Country</label>
                            <input type="text" class="form-control" id="editEventCountry" name="country" value="US">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="editEventTotalCapacity">Total Capacity</label>
                            <input type="number" class="form-control" id="editEventTotalCapacity" name="total_capacity">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="editEnableTicketing" name="enable_ticketing" value="1">
                            <label class="form-check-label" for="editEnableTicketing">Enable Ticketing</label>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="editEventTicketSalesStartAt">Ticket Sales Start</label>
                            <input type="datetime-local" class="form-control" id="editEventTicketSalesStartAt" name="ticket_sales_start_at">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="editEventTicketSalesEndAt">Ticket Sales End</label>
                            <input type="datetime-local" class="form-control" id="editEventTicketSalesEndAt" name="ticket_sales_end_at">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="editEventBasePrice">Base Price (Cents)</label>
                            <input type="number" class="form-control" id="editEventBasePrice" name="base_price_cents">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="editEventNgnAllocation">NGN Allocation</label>
                            <input type="number" class="form-control" id="editEventNgnAllocation" name="ngn_allocation">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="editEventStatus">Status</label>
                        <select class="form-control" id="editEventStatus" name="status">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editEventCancellationReason">Cancellation Reason</label>
                        <input type="text" class="form-control" id="editEventCancellationReason" name="cancellation_reason">
                    </div>
                    <div class="form-group">
                        <label for="editEventImageUrl">Image URL</label>
                        <input type="url" class="form-control" id="editEventImageUrl" name="image_url">
                    </div>
                    <div class="form-group">
                        <label for="editEventBannerUrl">Banner URL</label>
                        <input type="url" class="form-control" id="editEventBannerUrl" name="banner_url">
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="editIsAllAges" name="is_all_ages" value="1">
                            <label class="form-check-label" for="editIsAllAges">All Ages Event</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="editAgeRestriction">Age Restriction</label>
                        <input type="text" class="form-control" id="editAgeRestriction" name="age_restriction" placeholder="e.g., 21+, 18+">
                    </div>
                    <div class="form-group">
                        <label for="editEventMetadata">Metadata (JSON)</label>
                        <textarea class="form-control" id="editEventMetadata" name="metadata" rows="3">{}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Lineup Management Modal -->
<div class="modal fade" id="lineupModal" tabindex="-1" role="dialog" aria-labelledby="lineupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="lineupModalLabel">Manage Lineup for <span id="lineupEventTitle"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="lineupEventId">
                <h6>Current Lineup</h6>
                <div id="currentLineupList" class="list-group mb-3">
                    <!-- Lineup items will be loaded here -->
                </div>

                <h6>Add Artist to Lineup</h6>
                <form id="addLineupForm" class="form-row">
                    <div class="form-group col-md-6">
                        <label for="lineupArtistName">Artist Name</label>
                        <input type="text" class="form-control" id="lineupArtistName" name="artist_name" required>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="lineupPosition">Position</label>
                        <input type="number" class="form-control" id="lineupPosition" name="position" value="0">
                    </div>
                    <div class="form-group col-md-3 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="lineupIsHeadliner" name="is_headliner" value="1">
                            <label class="form-check-label" for="lineupIsHeadliner">Headliner</label>
                        </div>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="lineupSetTime">Set Time</label>
                        <input type="datetime-local" class="form-control" id="lineupSetTime" name="set_time">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="lineupSetLength">Set Length (minutes)</label>
                        <input type="number" class="form-control" id="lineupSetLength" name="set_length_minutes">
                    </div>
                    <div class="form-group col-md-12">
                        <label for="lineupNotes">Notes</label>
                        <textarea class="form-control" id="lineupNotes" name="notes" rows="2"></textarea>
                    </div>
                    <div class="form-group col-md-12">
                        <button type="submit" class="btn btn-success">Add to Lineup</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>