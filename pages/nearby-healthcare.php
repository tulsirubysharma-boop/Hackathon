<?php
$pageTitle = 'Nearby healthcare';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-section">
    <div class="section-heading">
        <p class="eyebrow">FIND IN-PERSON CARE</p>
        <h1>Nearby healthcare</h1>
        <p>Use your device location to open nearby doctors, clinics, and hospitals in a map service.</p>
    </div>
    <div class="profile-card">
        <h2>Find hospitals and doctors near you</h2>
        <p>After you grant location permission, your coordinates are sent to Google Maps to show the nearby map and search results.</p>
        <button class="btn btn-primary" type="button" id="findHealthcare">Use my current location</button>
        <p id="locationStatus" role="status" aria-live="polite"></p>
        <div class="hero-actions" id="mapLinks" hidden>
            <a class="btn btn-primary" id="googleMaps">Find nearby hospitals in Google Maps</a>
            <a class="btn btn-primary" id="pharmacyMaps">Find nearby medical stores in Google Maps</a>
        </div>
        <iframe id="currentLocationMap" title="Google Maps showing your current location"
                loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                hidden style="width:100%;height:380px;margin-top:20px;border:0;border-radius:16px"></iframe>
    </div>
    <div class="disclaimer" style="margin-top:24px">
        <strong>Map results are third-party listings, not a medical assessment.</strong>
        Confirm availability and credentials directly with the provider. For emergencies in India, call <a href="tel:112">112</a>.
    </div>
</section>
<script>
(() => {
    const button = document.getElementById('findHealthcare');
    const status = document.getElementById('locationStatus');
    const links = document.getElementById('mapLinks');
    button.addEventListener('click', () => {
        if (!navigator.geolocation) {
            status.textContent = 'Location is not supported by this browser. You can search your area directly in a map app.';
            return;
        }
        status.textContent = 'Waiting for location permission…';
        navigator.geolocation.getCurrentPosition(position => {
            const { latitude, longitude } = position.coords;
            const hospitalQuery = encodeURIComponent(`nearest hospital near ${latitude},${longitude}`);
            const pharmacyQuery = encodeURIComponent(`nearest pharmacy medical store near ${latitude},${longitude}`);
            document.getElementById('googleMaps').href = `https://www.google.com/maps/search/${hospitalQuery}`;
            document.getElementById('pharmacyMaps').href = `https://www.google.com/maps/search/${pharmacyQuery}`;
            const map = document.getElementById('currentLocationMap');
            map.src = `https://maps.google.com/maps?q=${latitude},${longitude}&z=14&output=embed`;
            map.hidden = false;
            links.hidden = false;
            status.textContent = 'Location found. Choose a map service to see nearby facilities.';
        }, error => {
            status.textContent = error.code === error.PERMISSION_DENIED
                ? 'Location permission was denied. Allow location access or search your area in a map app.'
                : 'Could not get your location. Please try again or search your area in a map app.';
        }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 });
    });
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
