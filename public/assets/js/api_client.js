const API_URL = '../api/';
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

const apiClient = {
    get: async (endpoint) => {
        try {
            const response = await fetch(`${API_URL}${endpoint}`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return await response.json();
        } catch (error) {
            return null;
        }
    },
    post: async (endpoint, data) => {
        try {
            const response = await fetch(`${API_URL}${endpoint}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: JSON.stringify(data)
            });
            // Los endpoints responden JSON con {status, message} tanto en éxito como en error
            // (4xx incluido, p. ej. create_cita.php con 400/403). No se debe descartar el cuerpo
            // solo porque el status HTTP no sea 2xx, o se pierde el motivo real del error.
            return await response.json();
        } catch (error) {
            return null;
        }
    }
};