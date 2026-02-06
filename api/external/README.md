
# Leads CRM External API

Easily connect your application or service to the Leads CRM platform using this RESTful API. The API enables secure management of leads, webhooks, custom fields, and reporting for your organization.

## Base URL

```
http://crm.zingbot.io/api/external/
```

## Authentication

All requests require authentication. Follow these steps:

### 1. Obtain an API Key
Each organization has a unique API key. Admins can generate or regenerate this key from the CRM dashboard, or via:

```
POST /api/admin/generate_api_key.php
Body: { "org_id": <orgId> }
```

### 2. Request an Access Token
Authenticate using your API key and organization ID:

**Endpoint:** `POST /auth.php`

**Request Body:**
```json
{
  "api_key": "your-api-key",
  "org_id": 1
}
```

**Response:**
```json
{
  "success": true,
  "token": "generated-token",
  "expires_at": "2024-01-01 12:00:00",
  "organization": "Your Org Name"
}
```

### 3. Authorize API Requests
Include the returned token in the `Authorization` header for all subsequent requests:

```
Authorization: Bearer your-token
```


## Endpoints

### Leads Management

#### List Leads
**GET** `/leads.php`

Query Parameters:
- `limit` (optional, max 100): Number of leads to return
- `offset` (optional): Pagination offset
- `search` (optional): Search by name, email, or company
- `stage_id` (optional): Filter by stage

**Example:**
```bash
curl -H "Authorization: Bearer your-token" \
  "http://your-domain.com/api/external/leads.php?limit=10&offset=0"
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "company": "Acme Inc",
      "phone": "555-1234",
      "source": "Website",
      "stage_id": 1,
      "lead_value": 5000,
      "created_at": "2024-01-01 10:00:00"
    }
  ],
  "meta": {
    "total": 100,
    "limit": 10,
    "offset": 0
  }
}
```

#### Create a Lead
**POST** `/leads.php`

Request Body:
```json
{
  "name": "Jane Smith",
  "email": "jane@example.com",
  "company": "Tech Corp",
  "phone": "555-5678",
  "source": "API",
  "stage_id": 1,
  "lead_value": 3000,
  "custom_fields": {
    "Budget": "50000",
    "Deadline": "2024-12-31"
  }
}
```
*`custom_fields` is optional. Keys are custom field names.*

**Response:**
```json
{
  "success": true,
  "lead_id": 123,
  "message": "Lead created successfully"
}
```

#### Update a Lead
**PUT** `/leads.php`

Request Body:
```json
{
  "id": 123,
  "stage_id": 2,
  "lead_value": 5000,
  "custom_fields": {
    "Budget": "75000"
  }
}
```
*Only provided fields will be updated. `custom_fields` is optional.*

#### Delete a Lead
**DELETE** `/leads.php`

Request Body:
```json
{
  "id": 123
}
```

#### Stage IDs
Use these IDs for the `stage_id` field:

- 1: NEW
- 2: CONTACTED
- 3: QUALIFIED
- 4: PROPOSAL
- 5: CLOSED WON


### Webhooks

Webhooks allow your application to receive real-time notifications about lead events in your organization.

#### List Webhooks
**GET** `/webhooks.php`

Retrieve all webhooks registered for your organization.

#### Create a Webhook
**POST** `/webhooks.php`

Request Body:
```json
{
  "url": "https://your-app.com/webhook",
  "events": ["lead.created", "lead.updated", "lead.stage_changed"]
}
```

Available Events:
- `lead.created`
- `lead.updated`
- `lead.deleted`
- `lead.stage_changed`

**Response:**
```json
{
  "success": true,
  "webhook_id": 1,
  "secret": "webhook-secret-key",
  "message": "Webhook created successfully"
}
```

#### Delete a Webhook
**DELETE** `/webhooks.php`

Request Body:
```json
{
  "id": 1
}
```

#### Webhook Payload Example
When an event occurs, your webhook URL will receive a POST request like:
```json
{
  "event": "lead.created",
  "timestamp": "2024-01-01T10:00:00Z",
  "org_id": 1,
  "data": {
    "id": 123,
    "name": "John Doe",
    "email": "john@example.com"
  }
}
```

Verify authenticity using the `X-Webhook-Signature` header and your webhook secret.


### Custom Fields

Custom fields let you extend lead records with additional data specific to your organization.

#### List Custom Fields
**GET** `/custom_fields.php`

Returns all custom fields for your organization.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "field_name": "Budget",
      "field_type": "number",
      "field_options": null,
      "is_required": 1
    }
  ]
}
```

#### Create a Custom Field
**POST** `/custom_fields.php`

Request Body:
```json
{
  "field_name": "Budget",
  "field_type": "number",
  "is_required": true,
  "field_options": "Option1,Option2" // for select type only
}
```

Field Types: `text`, `number`, `date`, `select`, `textarea`

#### Delete a Custom Field
**DELETE** `/custom_fields.php`

Request Body:
```json
{
  "id": 1
}
```


### Reports

Generate reports to analyze your leads and sales pipeline.

#### Get a Report
**GET** `/reports.php?type={report_type}`

Report Types:
- `summary`: Overall statistics
- `conversion`: Conversion by stage
- `source`: Leads by source

**Example:**
```bash
curl -H "Authorization: Bearer your-token" \
  "http://your-domain.com/api/external/reports.php?type=summary"
```

**Response (summary):**
```json
{
  "success": true,
  "data": {
    "total_leads": 500,
    "leads_last_30_days": 50,
    "converted_leads": 100,
    "avg_lead_value": 4500,
    "total_value": 2250000
  }
}
```

### Error Handling

All error responses use this format:
```json
{
  "error": "Error message description"
}
```

Common HTTP Status Codes:
- 400: Bad Request
- 401: Unauthorized
- 404: Not Found
- 405: Method Not Allowed
- 500: Internal Server Error

### Rate Limiting
No rate limiting is currently enforced. For production deployments, consider implementing rate limiting to protect your API.

## Webhook Payload Example

When an event occurs, your webhook URL will receive:
```json
{
  "event": "lead.created",
  "timestamp": "2024-01-01T10:00:00Z",
  "org_id": 1,
  "data": {
    "id": 123,
    "name": "John Doe",
    "email": "john@example.com"
  }
}
```

Verify webhook authenticity using the `X-Webhook-Signature` header with your webhook secret.


## Setup Instructions

1. **Run the Database Migration**
   
  Import the required tables for the external API:
  ```bash
  mysql -u root -p crm_app < database/migrations/add_external_api_tables.sql
  ```

2. **Obtain Your API Key**
   
  Log in to the CRM dashboard and navigate to Organization Settings to generate or view your API key.

3. **Authenticate and Get a Token**
   
  Test authentication with your API key and organization ID:
  ```bash
  curl -X POST http://your-domain.com/api/external/auth.php \
    -H "Content-Type: application/json" \
    -d '{"api_key":"your-api-key","org_id":1}'
  ```

  The response will include a `token` to use in the `Authorization` header for all API requests.

4. **Make API Requests**
   
  Use the token in the `Authorization: Bearer your-token` header for all endpoints.