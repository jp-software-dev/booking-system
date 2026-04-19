/**
 * CLIENTE API PARA PETICIONES FETCH
 *
 * Proporciona una interfaz simplificada para realizar peticiones GET y POST
 * a los endpoints del backend, centralizando la lógica de manejo de errores.
 */

const API_URL = '../api/';

const apiClient = {
    // GET: obtiene datos del endpoint.
    get: async (endpoint) => {
        try {
            const response = await fetch(`${API_URL}${endpoint}`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return await response.json();
        } catch (error) {
            console.error(`[API GET Error] ${endpoint}:`, error);
            return null;
        }
    },
    // POST: envía datos al endpoint.
    post: async (endpoint, data) => {
        try {
            const response = await fetch(`${API_URL}${endpoint}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return await response.json();
        } catch (error) {
            console.error(`[API POST Error] ${endpoint}:`, error);
            return null;
        }
    }
};