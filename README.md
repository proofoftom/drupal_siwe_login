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
4. Configure at `/admin/config/people/siwe` or through the admin menu under "Configuration > People > SIWE Login"

## API Endpoints

- `GET /siwe/nonce` - Get authentication nonce
- `POST /siwe/verify` - Verify SIWE message and authenticate

## Configuration

See `/admin/config/people/siwe` for configuration options.

### Settings

The SIWE Login module handles domain validation based on the configuration set by the SIWE Server module. When used standalone, it validates against the current Drupal host. When used with SIWE Server, it validates against the domains configured in the SIWE Server settings. The following settings are available:

- **Nonce TTL**: Time-to-live for nonces in seconds
- **Message TTL**: Time-to-live for SIWE messages in seconds
- **Allow Registration**: Allow new users to register using SIWE
- **Require Email Verification**: Require email verification for new users
- **Require ENS or Username**: Require users to set a username if they don't have an ENS name
- **Session Timeout**: Session timeout in seconds
- **Ethereum Provider URL**: URL for the Ethereum RPC provider (Alchemy, Infura, etc.) for ENS validation

When "Require Email Verification" is enabled, new users will be directed to an email verification form during their first login. Existing users without a verified email address will also be prompted to provide one.

When "Require ENS or Username" is enabled, new users without an ENS name will be directed to a username creation form during their first login.

## Email Verification (optional)

When email verification is required, the following flow occurs:

1. User signs SIWE message with their Ethereum wallet
2. If the user doesn't exist or doesn't have a verified email, they are redirected to an email verification form
3. User provides their email address
4. A verification email is sent to the provided address
5. User clicks the verification link in the email
6. User account is created/updated and the user is logged in

## Username Creation (optional)

When username creation is required, the following flow occurs:

1. User signs SIWE message with their Ethereum wallet
2. If the user doesn't have an ENS name and doesn't have a custom username, they are redirected to a username creation form
3. User provides their desired username
4. User account is created with the provided username and the user is logged in

### ENS Validation (optional)

When an ENS name is provided in the SIWE message resources (in the format `ens:{ens-name}`) and validation is enabled, the module validates that the ENS name resolves to the signing Ethereum address using the ENS contracts on Ethereum mainnet.

This validation requires an Ethereum RPC provider URL to be configured in the settings. To get an Ethereum provider URL, you can sign up for a free account with services like [Alchemy](https://www.alchemy.com/) or [Infura](https://infura.io/).

## Security

- Uses EIP-191 message signing standard
- Implements nonce-based replay attack prevention
- Configurable token expiration
- Email verification for new users when enabled
- ENS name validation against Ethereum mainnet when provided

## Support

Report issues at: [https://github.com/proofoftom/drupal_siwe_login/issues](https://github.com/proofoftom/drupal_siwe_login/issues)

## Implementation Details

This module implements the SIWE authentication flow:

1. A nonce is generated and stored in the cache
2. The user signs a SIWE message with their Ethereum wallet
3. The signature is verified using the kornrunner/keccak and simplito/elliptic-php libraries
4. If the signature is valid:
   - If email verification is required and the user doesn't exist or doesn't have a verified email, they are redirected to an email verification form
   - If username creation is required and the user doesn't have an ENS name, they are redirected to a username creation form
   - Otherwise, a user account is created or updated with the Ethereum address
5. The user is logged in

## Field Requirements

This module requires the following fields to be configured for the user entity:

- `field_ethereum_address` - Stores the Ethereum address associated with the user account
- `field_ens_name` (optional) - Stores the ENS name associated with the user account

These fields are automatically created when importing the configuration.
