// Gemeinsame Fußgänger-Routenberechnung (Valhalla) für Stationsstrecken.
// Ersetzt OSRM: OSRM entfernt kleine, isolierte Wald-/Feldwege aus seinem
// Routing-Graphen (Small-Component-Pruning), Valhalla behandelt sie als nutzbar.

const WT_VALHALLA_URL = 'https://valhalla1.openstreetmap.de/route';

/**
 * Berechnet eine Fußgänger-Route über eine geordnete Liste von Punkten.
 * @param {Array<[number,number]>} coords - Punkte als [lng, lat]-Paare (Start, ggf. Wegpunkte, Ziel)
 * @param {Object} [opts]
 * @param {boolean} [opts.geometry=false] - Routen-Geometrie mitliefern (für Kartendarstellung)
 * @param {number}  [opts.timeoutMs=8000]
 * @returns {Promise<{distanceM:number, durationMin:number, latlngs:Array<[number,number]>|null}>}
 */
async function wtCalcRoute(coords, { geometry = false, timeoutMs = 8000 } = {}) {
    const body = {
        locations: coords.map(([lng, lat]) => ({ lat, lon: lng })),
        costing: 'pedestrian',
    };
    const url = `${WT_VALHALLA_URL}?json=${encodeURIComponent(JSON.stringify(body))}`;

    const res = await fetch(url, { signal: AbortSignal.timeout(timeoutMs) });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();

    if (!data.trip || data.trip.status !== 0 || !data.trip.legs?.length) {
        throw new Error('Keine Route gefunden');
    }

    const distanceM   = Math.round(data.trip.summary.length * 1000);
    const durationMin = Math.max(1, Math.round(data.trip.summary.time / 60));

    let latlngs = null;
    if (geometry) {
        latlngs = [];
        for (const leg of data.trip.legs) {
            latlngs.push(...wtDecodePolyline6(leg.shape));
        }
    }

    return { distanceM, durationMin, latlngs };
}

// Decodiert eine Valhalla-Polyline (Precision 1e6) zu [lat,lng]-Paaren.
function wtDecodePolyline6(encoded) {
    let index = 0, lat = 0, lng = 0;
    const coordinates = [];
    while (index < encoded.length) {
        let shift = 0, result = 0, byte;
        do {
            byte = encoded.charCodeAt(index++) - 63;
            result |= (byte & 0x1f) << shift;
            shift += 5;
        } while (byte >= 0x20);
        lat += (result & 1) ? ~(result >> 1) : (result >> 1);

        shift = 0; result = 0;
        do {
            byte = encoded.charCodeAt(index++) - 63;
            result |= (byte & 0x1f) << shift;
            shift += 5;
        } while (byte >= 0x20);
        lng += (result & 1) ? ~(result >> 1) : (result >> 1);

        coordinates.push([lat / 1e6, lng / 1e6]);
    }
    return coordinates;
}
