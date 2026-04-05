# Cash Detector Dummy (Python)

Dummy backend for cash authenticity detection. This service is designed to run on EC2 later and can already be connected from the PHP web app.

## Run local

```bash
cd services/cash-detector
python -m venv .venv
.venv\\Scripts\\activate
pip install -r requirements.txt
uvicorn app:app --host 0.0.0.0 --port 8000
```

## Endpoints

- `GET /health`
- `POST /detect`

Example body:

```json
{
  "order_id": "ORDER-TEST-123",
  "amount": 10000,
  "currency": "IDR",
  "simulate": true
}
```

## Detector behavior

Use env `DETECTOR_MODE`:

- `random` (default)
- `genuine`
- `counterfeit`
- `uncertain`

## Connect from PHP app

Set in `.env`:

```env
CASH_DETECTOR_URL=http://YOUR_EC2_PUBLIC_IP:8000
CASH_DETECTOR_ENDPOINT=/detect
```

If `CASH_DETECTOR_URL` is empty, PHP will use local dummy detector mode.
