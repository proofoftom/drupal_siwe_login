(function (Drupal, drupalSettings) {
  "use strict";

  Drupal.behaviors.siweLogin = {
    attach: function (context, settings) {
      console.log("SIWE Login behavior attached to context:", context);
      const button = context.querySelector("#siwe-login-button");
      console.log("Button found:", button);

      if (!button) return;

      button.addEventListener("click", async function () {
        try {
          // Check for Web3 provider
          if (typeof window.ethereum === "undefined") {
            throw new Error("Please install MetaMask or another Web3 wallet");
          }

          // Request account access
          const accounts = await window.ethereum.request({
            method: "eth_requestAccounts",
          });
          const address = accounts[0];

          // Get nonce from server
          const nonceResponse = await fetch("/siwe/nonce");
          const { nonce } = await nonceResponse.json();

          // Create SIWE message
          const message = createSiweMessage({
            domain: window.location.host,
            address: address,
            statement: "Sign in with Ethereum to Drupal",
            uri: window.location.origin,
            version: "1",
            chainId: 1,
            nonce: nonce,
            issuedAt: new Date().toISOString(),
          });

          // Sign message
          const signature = await window.ethereum.request({
            method: "personal_sign",
            params: [message, address],
          });

          // Verify with server
          const verifyResponse = await fetch("/siwe/verify", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
            },
            body: JSON.stringify({
              message: message,
              signature: signature,
              address: address,
              nonce: nonce,
            }),
          });

          const result = await verifyResponse.json();

          if (result.success) {
            // Redirect or update UI
            window.location.reload();
          }
        } catch (error) {
          console.error("SIWE authentication failed:", error);
        }
      });
    },
  };

  function createSiweMessage(params) {
    return `${params.domain} wants you to sign in with your Ethereum account:
${params.address}

${params.statement}

URI: ${params.uri}
Version: ${params.version}
Chain ID: ${params.chainId}
Nonce: ${params.nonce}
Issued At: ${params.issuedAt}`;
  }
})(Drupal, drupalSettings);
