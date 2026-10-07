import React, { useContext, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { Dropdown } from "react-bootstrap";
import { useDispatch } from "react-redux";
import { ThemeContext } from "../../../context/ThemeContext";
import { Logout } from "../../../store/actions/AuthActions";
import profile from "../../../assets/images/profile/pic1.jpg";

function readUserSession() {
  try {
    const raw = localStorage.getItem("userDetails");
    if (!raw) return { name: "Guest", email: "", role: "" };
    const stored = JSON.parse(raw);
    return {
      name: stored.displayName || stored.user?.name || stored.email || "User",
      email: stored.email || stored.user?.email || "",
      role: stored.roles?.[0]?.name || stored.user?.roles?.[0]?.name || "",
    };
  } catch {
    return { name: "Guest", email: "", role: "" };
  }
}

const Header = () => {
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const [session] = useState(() => readUserSession());
  const path = window.location.pathname.split("/");
  const name = path[path.length - 1].split("-");
  const filterName = name.length >= 3 ? name.filter((n, i) => i > 0) : name;
  const finalName = filterName.includes("app")
    ? filterName.filter((f) => f !== "app")
    : filterName.includes("ui")
      ? filterName.filter((f) => f !== "ui")
      : filterName.includes("uc")
        ? filterName.filter((f) => f !== "uc")
        : filterName.includes("basic")
          ? filterName.filter((f) => f !== "basic")
          : filterName.includes("page")
            ? filterName.filter((f) => f !== "page")
            : filterName.includes("email")
              ? filterName.filter((f) => f !== "email")
              : filterName;

  const { background, changeBackground } = useContext(ThemeContext);

  function ChangeColor() {
    if (background.value === "light") {
      changeBackground({ value: "dark", label: "Dark" });
    } else {
      changeBackground({ value: "light", label: "Light" });
    }
  }

  const onLogout = () => {
    dispatch(Logout(navigate));
  };

  const pageTitle =
    finalName.join(" ").length === 0
      ? "Dashboard"
      : finalName.join(" ") === "dashboard dark"
        ? "Dashboard"
        : finalName.join(" ");

  return (
    <div className="header">
      <div className="header-content">
        <nav className="navbar navbar-expand">
          <div className="collapse navbar-collapse justify-content-between">
            <div className="header-left">
              <div className="dashboard_bar" style={{ textTransform: "capitalize" }}>
                {pageTitle}
              </div>
            </div>
            <ul className="navbar-nav header-right main-notification">
              <li className="nav-item dropdown notification_dropdown">
                <Link
                  to={"#"}
                  className={`nav-link bell dz-theme-mode p-0 ${background.value === "dark" ? "active" : ""}`}
                  onClick={ChangeColor}
                >
                  <i id="icon-light" className="fas fa-sun" />
                  <i id="icon-dark" className="fas fa-moon" />
                </Link>
              </li>

              <Dropdown as="li" className="nav-item dropdown header-profile">
                <Dropdown.Toggle
                  variant=""
                  as="a"
                  className="nav-link i-false c-pointer"
                  role="button"
                  data-toggle="dropdown"
                >
                  <img src={profile} width={20} alt="" />
                  <div className="header-info ms-2 d-none d-sm-block">
                    <span className="font-w600 d-block lh-1">{session.name}</span>
                    <small className="text-muted">{session.role || "IRCUB user"}</small>
                  </div>
                </Dropdown.Toggle>
                <Dropdown.Menu align="end" className="mt-2 dropdown-menu dropdown-menu-end">
                  <div className="dropdown-item-text px-3 py-2 border-bottom">
                    <strong className="d-block">{session.name}</strong>
                    <small className="text-muted">{session.email}</small>
                  </div>
                  <button type="button" className="dropdown-item ai-icon" onClick={onLogout}>
                    <i className="fa fa-sign-out text-danger me-2" />
                    <span>Logout</span>
                  </button>
                </Dropdown.Menu>
              </Dropdown>
            </ul>
          </div>
        </nav>
      </div>
    </div>
  );
};

export default Header;
