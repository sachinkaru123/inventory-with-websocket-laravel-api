// Test CORS from your frontend JavaScript console
fetch('http://192.168.8.111:8000/api/items', {
    method: 'GET',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    },
    credentials: 'include' // Include cookies/auth if needed
})
.then(response => response.json())
.then(data => console.log('CORS test successful:', data))
.catch(error => console.error('CORS test failed:', error));
