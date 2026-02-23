const TOKEN_KEY = 'parking_auth_token';
const USER_KEY = 'parking_auth_user';

const getApiBase = () => {
	// Vite dev server proxies API routes; production uses same origin
	return '';
};

export default {
	async login(emailOrUsername, password) {
		const res = await fetch(`${getApiBase()}/login`, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				email: emailOrUsername,
				username: emailOrUsername,
				password
			})
		});
		const data = await res.json().catch(() => ({}));
		if (!res.ok) {
			throw new Error(data.error || 'Invalid credentials');
		}
		sessionStorage.setItem(TOKEN_KEY, data.token);
		sessionStorage.setItem(USER_KEY, data.user?.email || emailOrUsername);
		return { success: true, user: data.user };
	},

	logout() {
		sessionStorage.removeItem(TOKEN_KEY);
		sessionStorage.removeItem(USER_KEY);
	},

	isLoggedIn() {
		return !!sessionStorage.getItem(TOKEN_KEY);
	},

	getCurrentUser() {
		return sessionStorage.getItem(USER_KEY) || '';
	},

	getToken() {
		return sessionStorage.getItem(TOKEN_KEY);
	}
};
