import React from "react";

const Footer = () => {
  const year = new Date().getFullYear();
  return (
    <div className="footer">
      <div className="copyright">
        <p>
          IRCUB © {year} — Farsight Africa Technologies POC
        </p>
      </div>
    </div>
  );
};

export default Footer;
