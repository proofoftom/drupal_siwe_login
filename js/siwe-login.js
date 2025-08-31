(function (Drupal, drupalSettings) {
  "use strict";

  Drupal.behaviors.siweLogin = {
    attach: function (context, settings) {
      const button = context.querySelector("#siwe-login-button");

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
          const chainId = await window.ethereum.request({
            method: "eth_chainId",
          });

          // Create SIWE message
          const message = new SiweMessage({
            domain: window.location.host,
            address,
            statement: "Sign in with Ethereum to Drupal",
            uri: window.location.origin,
            version: "1",
            chainId: parseInt(chainId, 16),
            nonce: nonce,
            issuedAt: new Date().toISOString(),
          });
          const preparedMessage = message.prepareMessage();

          // Sign message
          // TODO: For whatever reason this appears in MetaMask as a "Signature request" whereas
          // when using ethers.js in @next/components/auth/SiweLogin.tsx it appears as a "Sign-in request" - research further
          const signature = await window.ethereum.request({
            method: "personal_sign",
            params: [preparedMessage, address],
          });

          // Verify with server
          const verifyResponse = await fetch("/siwe/verify", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
            },
            body: JSON.stringify({
              message: preparedMessage,
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
})(Drupal, drupalSettings);
