import http from 'k6/http';
import { check, sleep } from 'k6';

export let options = {
  vus: 50,
  duration: '30s',
};

export default function () {
  const url = `${__ENV.APP_URL}/api/auth/login`;
  const payload = JSON.stringify({
    email: 'loadtest@example.com',
    password: 'LoadTestPass123'
  });
  const headers = { 'Content-Type': 'application/json' };
  const res = http.post(url, payload, { headers });
  check(res, {
    'is status 200 or 401': (r) => r.status === 200 || r.status === 401
  });
  sleep(1);
}
