# Security Policy

## Supported Versions

We actively support the latest stable version of the CRM Dental system. Security updates are provided for:

| Version | Supported          |
| ------- | ------------------ |
| Latest  | :white_check_mark: |
| < Latest| :x:                |

## Reporting a Vulnerability

If you discover a security vulnerability, please **do not** open a public issue. Instead, please report it via one of the following methods:

1. **Email**: Send details to security@crmdental.com (or your security contact email)
2. **Private Issue**: If using a private repository, create a private security issue

### What to Include

When reporting a vulnerability, please include:

- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if available)
- Your contact information

### Response Time

We aim to:
- Acknowledge receipt within 48 hours
- Provide an initial assessment within 7 days
- Keep you informed of our progress
- Release a fix as soon as possible (timeline depends on severity)

### Disclosure Policy

- We will coordinate disclosure with you
- We will credit you for the discovery (if desired)
- We will not disclose your identity without permission

## Security Best Practices

When deploying this application:

1. **Never commit secrets**: Use `.env` files (which are gitignored) for sensitive configuration
2. **Rotate keys**: Change `APP_KEY` and all API keys after cloning/deploying
3. **Use HTTPS**: Always use HTTPS in production
4. **Keep dependencies updated**: Regularly run `composer update` and `npm update`
5. **Review permissions**: Ensure proper file permissions on `storage/` and `bootstrap/cache/`
6. **Enable rate limiting**: Configure appropriate rate limits for your use case
7. **Monitor logs**: Regularly review application logs for suspicious activity

## Known Security Considerations

- This application uses Laravel Sanctum for API authentication
- Rate limiting is enabled by default (60 req/min for API, 5 req/min for login)
- CORS is configured - ensure `FRONTEND_URL` and `SANCTUM_STATEFUL_DOMAINS` are set correctly
- Audit logging is enabled for sensitive operations

## Security Updates

Security updates will be:
- Tagged with version numbers
- Documented in CHANGELOG.md (if maintained)
- Announced via repository releases

Thank you for helping keep CRM Dental secure!

