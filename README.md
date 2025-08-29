# SIWE Authentication for Drupal

## Overview

This module provides Ethereum wallet-based authentication for Drupal using the Sign-In with Ethereum (SIWE) standard.

## Requirements

- Drupal 10.0 or higher
- PHP 8.1 or higher
- Composer

## Installation

1. Install via Composer: `composer require drupal/siwe_login`
2. Enable modules: `drush en siwe_login -y`
3. Import configuration: `drush config-import --partial --source=modules/custom/siwe_login/config/install`
4. Configure at `/admin/config/people/siwe`

## API Endpoints

- `GET /siwe/nonce` - Get authentication nonce
- `POST /siwe/verify` - Verify SIWE message and authenticate
- `POST /siwe/logout` - Logout user

## Configuration

See `/admin/config/people/siwe` for configuration options.

## Security

- Uses EIP-191 message signing standard
- Implements nonce-based replay attack prevention
- Configurable token expiration

## Support

Report issues at: https://github.com/your-org/siwe_drupal/issues

## Implementation Details

This module implements the SIWE authentication flow:

1. A nonce is generated and stored in the cache
2. The user signs a SIWE message with their Ethereum wallet
3. The signature is verified using the kornrunner/keccak and simplito/elliptic-php libraries
4. If the signature is valid, a user account is created or updated with the Ethereum address
5. The user is logged in

## Field Requirements

This module requires the following fields to be configured for the user entity:

- `field_ethereum_address` - Stores the Ethereum address associated with the user account
- `field_ens_name` (optional) - Stores the ENS name associated with the user account

These fields are automatically created when importing the configuration.