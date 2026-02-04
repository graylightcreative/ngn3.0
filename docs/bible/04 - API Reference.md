4. API Reference (v1)

4.1 Standards

Base URL: https://api.nextgennoise.com/api/v1

Format: JSON Only.

Date Format: ISO 8601 (YYYY-MM-DDTHH:mm:ssZ).

Protocol: REST Level 2 (Resource-oriented).

Response Envelope

{
  "data": { ... },       // The resource(s)
  "meta": {              // Pagination & metadata
    "current_page": 1,
    "total_pages": 5
  },
  "error": null          // Or { "code": 404, "message": "..." }
}


4.2 Authentication

Method: JWT (JSON Web Token) via Bearer Auth header.

Endpoints:

POST /auth/login - Returns access_token and refresh_token.

POST /auth/refresh - Rotate tokens.

POST /auth/password-reset/request

GET /auth/me - Current user context & permissions.

4.3 Core Resources

Users & Identity

GET /users - List users (Admin filterable).

GET /users/{id}

GET /users/{id}/oauth_tokens - Check connection status (FB, Spotify).

Artists

GET /artists - Public directory. Filter by genre, name.

GET /artists/{id} - Profile data.

GET /artists/{id}/releases

GET /artists/{id}/stats - Aggregated public stats (NGN Score history).

Stations

GET /stations

GET /stations/{id}/spins - Recent airplay.

POST /stations/{id}/spins - (Auth: Station) Submit real-time spin.

Venues & Events

GET /venues - Directory of venues. Filter by city, state.

GET /venues/{id} - Venue profile (Capacity, Map, Bio).

GET /venues/{id}/events - Calendar of upcoming shows.

POST /venues/{id}/events - (Auth: Venue) Create a new show.

PUT /events/{id} - (Auth: Venue) Update show status (e.g., cancel).

Rankings (Read-Model)

GET /rankings/charts - List available charts (e.g., ngn:weekly, smr:legacy).

GET /rankings/charts/{slug}/current - The latest chart.

GET /rankings/charts/{slug}/{date} - Historic charts.

4.4 Commerce & QR

Since Commerce backend is complete (cw_80-cw_83), these are live endpoints.

GET /products - List merchandise.

POST /orders - Create order (Guest or Auth).

POST /checkout/session - Initialize Stripe.

GET /qr/{entity_type}/{entity_id} - Generates a dynamic QR code pointing to the entity's public URL.

Parameters: format=png|svg, size=300.

Supports: Artists, Stations, Venues.

4.5 SMR Ingestion (Admin)

POST /smr/upload - Upload CSV/Excel.

Body: Multipart file form-data.

GET /smr/ingestions/{id} - Check processing status.

POST /smr/ingestions/{id}/resolve - Manually map an unknown artist string to a UUID.