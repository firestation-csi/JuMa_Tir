-- Zwischenpunkte je Routenabschnitt als GeoJSON-Array [[lat,lng], ...]
ALTER TABLE station_routes
    ADD COLUMN waypoints JSON NULL AFTER notes;
