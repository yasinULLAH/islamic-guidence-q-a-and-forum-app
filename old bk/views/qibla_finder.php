<div class="container mt-5 text-center">
    <h1 class="mb-4">Qibla Direction Finder</h1>
    <p class="lead">Find the direction of the Kaaba in Mecca from your current location.</p>

    <div class="card mb-4">
        <div class="card-body">
            <p id="locationStatus" class="text-info">Getting your location...</p>
            <p id="qiblaDirection" class="h3 text-primary"></p>
            <p id="errorMsg" class="text-danger"></p>

            <div class="mt-4">
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/f/f3/Compass_rose_black.svg/1200px-Compass_rose_black.svg.png" alt="Compass" id="compass" style="width: 200px; height: 200px; transition: transform 0.5s ease-out;">
            </div>
            <p class="mt-3"><small class="text-muted">Ensure location services are enabled for accurate results.</small></p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const locationStatus = document.getElementById('locationStatus');
    const qiblaDirection = document.getElementById('qiblaDirection');
    const errorMsg = document.getElementById('errorMsg');
    const compass = document.getElementById('compass');

    // Coordinates of Kaaba in Mecca (Latitude, Longitude)
    const KAABA_LAT = 21.4225;
    const KAABA_LON = 39.8262;

    function toRadians(degrees) {
        return degrees * Math.PI / 180;
    }

    function toDegrees(radians) {
        return radians * 180 / Math.PI;
    }

    function calculateQiblaDirection(latitude, longitude) {
        const latUser = toRadians(latitude);
        const lonUser = toRadians(longitude);
        const latKaaba = toRadians(KAABA_LAT);
        const lonKaaba = toRadians(KAABA_LON);

        const deltaLon = lonKaaba - lonUser;

        const y = Math.sin(deltaLon);
        const x = Math.cos(latUser) * Math.tan(latKaaba) - Math.sin(latUser) * Math.cos(deltaLon);

        let bearing = toDegrees(Math.atan2(y, x));
        if (bearing < 0) {
            bearing += 360;
        }
        return bearing;
    }

    function getLocationAndCalculateQibla() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const userLat = position.coords.latitude;
                    const userLon = position.coords.longitude;
                    locationStatus.textContent = `Your Location: Latitude ${userLat.toFixed(4)}, Longitude ${userLon.toFixed(4)}`;

                    const qiblaBearing = calculateQiblaDirection(userLat, userLon);
                    qiblaDirection.textContent = `Qibla Direction: ${qiblaBearing.toFixed(2)}° from North`;
                    compass.style.transform = `rotate(${qiblaBearing}deg)`;
                },
                (error) => {
                    locationStatus.textContent = '';
                    switch (error.code) {
                        case error.PERMISSION_DENIED:
                            errorMsg.textContent = "Location access denied. Please enable location services in your browser settings.";
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMsg.textContent = "Location information is unavailable.";
                            break;
                        case error.TIMEOUT:
                            errorMsg.textContent = "The request to get user location timed out.";
                            break;
                        case error.UNKNOWN_ERROR:
                            errorMsg.textContent = "An unknown error occurred.";
                            break;
                    }
                }
            );
        } else {
            locationStatus.textContent = '';
            errorMsg.textContent = "Geolocation is not supported by your browser.";
        }
    }

    getLocationAndCalculateQibla();
});
</script>
