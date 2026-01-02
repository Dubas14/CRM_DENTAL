# Contributing to CRM Dental

Thank you for your interest in contributing to CRM Dental! This document provides guidelines and instructions for contributing.

## Code of Conduct

- Be respectful and inclusive
- Welcome newcomers and help them learn
- Focus on constructive feedback
- Respect different viewpoints and experiences

## Getting Started

1. **Fork the repository**
2. **Clone your fork**: `git clone https://github.com/your-username/CRM_DENTAL.git`
3. **Create a branch**: `git checkout -b feature/your-feature-name`
4. **Set up the development environment** (see README.md)

## Development Setup

### Backend (Laravel)

```bash
cd dental-crm-api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### Frontend (Vue)

```bash
cd dental-crm-frontend
npm install
cp .env.example .env
npm run dev
```

## Making Changes

### Branch Naming

- `feature/` - New features
- `fix/` - Bug fixes
- `docs/` - Documentation updates
- `refactor/` - Code refactoring
- `test/` - Test additions/updates

### Code Style

#### Backend (PHP/Laravel)

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards
- Use Laravel conventions
- Run `./vendor/bin/pint` before committing
- Add PHPDoc comments for public methods

#### Frontend (TypeScript/Vue)

- Use TypeScript for all new code
- Follow Vue 3 Composition API patterns
- Use `<script setup>` syntax
- Run `npm run lint` and `npm run format` before committing
- Add JSDoc comments for complex functions

### Commit Messages

Use clear, descriptive commit messages:

```
feat: Add appointment cancellation feature
fix: Resolve timezone issue in calendar slots
docs: Update API documentation
refactor: Simplify appointment conflict checking
```

### Testing

- Write tests for new features
- Ensure all existing tests pass: `php artisan test` (backend) or `npm run test` (frontend)
- Aim for meaningful test coverage

## Pull Request Process

1. **Update documentation** if needed
2. **Add/update tests** for your changes
3. **Ensure all tests pass**
4. **Update CHANGELOG.md** (if maintained) with your changes
5. **Create a Pull Request** with:
   - Clear description of changes
   - Reference to related issues (if any)
   - Screenshots (for UI changes)

### PR Checklist

- [ ] Code follows style guidelines
- [ ] Tests added/updated and passing
- [ ] Documentation updated
- [ ] No console.log/debug statements left
- [ ] No sensitive data in code
- [ ] .env files not committed

## Code Review

- All PRs require review before merging
- Address review comments promptly
- Be open to feedback and suggestions
- Keep PRs focused and reasonably sized

## Reporting Issues

When reporting bugs:

1. Use the issue template
2. Provide clear steps to reproduce
3. Include environment details (OS, PHP version, Node version)
4. Add relevant logs/error messages
5. Include screenshots if applicable

## Feature Requests

For feature requests:

1. Check if the feature already exists or is planned
2. Describe the use case clearly
3. Explain the expected behavior
4. Consider implementation complexity

## Questions?

- Open a discussion in the repository
- Check existing issues and PRs
- Review the documentation

Thank you for contributing to CRM Dental!

