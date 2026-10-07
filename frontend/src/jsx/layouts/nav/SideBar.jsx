import React, { useReducer, useContext, useEffect, useState } from "react";
/// Scroll
import PerfectScrollbar from "react-perfect-scrollbar";
/// Link
import { Link, useNavigate } from "react-router-dom";
import { Collapse } from "react-bootstrap";
import { useDispatch } from "react-redux";
import { useScrollPosition } from "@n8tb1t/use-scroll-position";
import { getVisibleMenu } from './Menu';
import { ThemeContext } from "../../../context/ThemeContext";
import { Logout } from "../../../store/actions/AuthActions";
/// Image
import profile from "../../../assets/images/profile/pic1.jpg";

function readUserSession() {
  try {
    const raw = localStorage.getItem('userDetails');
    if (!raw) return { permissions: [], name: 'Guest', email: '', role: '' };
    const stored = JSON.parse(raw);
    return {
      permissions: Array.isArray(stored.permissions) ? stored.permissions : [],
      name: stored.displayName || stored.user?.name || stored.email || 'User',
      email: stored.email || stored.user?.email || '',
      role: stored.roles?.[0]?.name || stored.user?.roles?.[0]?.name || '',
    };
  } catch {
    return { permissions: [], name: 'Guest', email: '', role: '' };
  }
}

const reducer = (previousState, updatedState) => ({
  ...previousState,
  ...updatedState,
});

const initialState = {
  active: "",
  activeSubmenu: "",
}

const SideBar = () => {
  let year = new Date().getFullYear();
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const [session] = useState(() => readUserSession());
  const MenuList = getVisibleMenu(session.permissions);
  const {
    iconHover,
    sidebarposition,
    headerposition,
    sidebarLayout,
    ChangeIconSidebar,
  } = useContext(ThemeContext);

  const [state, setState] = useReducer(reducer, initialState);

  const onLogout = () => {
    dispatch(Logout(navigate));
  };

  useEffect(() => {
    const btn = document.querySelector(".nav-control");
    const wrapper = document.querySelector("#main-wrapper");
    if (!btn || !wrapper) {
      return undefined;
    }
    const toggleFunc = () => wrapper.classList.toggle("menu-toggle");
    btn.addEventListener("click", toggleFunc);
    return () => btn.removeEventListener("click", toggleFunc);
  }, []);

  function heartBlast() {
    document.querySelector('.heart')?.classList.toggle("heart-blast");
  }
  const [hideOnScroll, setHideOnScroll] = useState(true)
  useScrollPosition(
    ({ prevPos, currPos }) => {
      const isShow = currPos.y > prevPos.y
      if (isShow !== hideOnScroll) setHideOnScroll(isShow)
    },
    [hideOnScroll]
  )


  const handleMenuActive = status => {
    setState({ active: status });
    if (state.active === status) {
      setState({ active: "" });
    }
  }
  const handleSubmenuActive = (status) => {
    setState({ activeSubmenu: status })
    if (state.activeSubmenu === status) {
      setState({ activeSubmenu: "" })
    }
  }

  /// Path
  let path = window.location.pathname;
  path = path.split("/");
  path = path[path.length - 1];
  /// Active menu

  return (
    <div
      onMouseEnter={() => ChangeIconSidebar(true)}
      onMouseLeave={() => ChangeIconSidebar(false)}
      className={`dlabnav ${iconHover} ${sidebarposition.value === "fixed" &&
        sidebarLayout.value === "horizontal" &&
        headerposition.value === "static"
        ? hideOnScroll > 120
          ? "fixed"
          : ""
        : ""
        }`}
    >
      <PerfectScrollbar className="dlabnav-scroll">
        <ul className="metismenu" id="menu">
          <li className="nav-item header-profile">
            <div className="nav-link">
              <img src={profile} width={20} alt="" />
              <div className="header-info ms-3">
                <span className="font-w600">Hi, <b>{session.name}</b></span>
                <small className="text-end font-w400">{session.email || 'IRCUB user'}</small>
                {session.role && (
                  <small className="d-block text-primary font-w500">{session.role}</small>
                )}
              </div>
            </div>
          </li>
          {MenuList.map((data, index) => {
            let menuClass = data.classsChange;
            if (menuClass === "menu-title") {
              return (
                <li className={menuClass} key={index} >{data.title}</li>
              )
            } else {
              return (
                <li className={` ${state.active === data.title ? 'mm-active' : ''}`}
                  key={index}
                >

                  {data.content && data.content.length > 0 ?
                    <>
                      <Link to={"#"}
                        className="has-arrow"
                        onClick={() => { handleMenuActive(data.title) }}
                      >
                        {data.iconStyle}
                        <span className="nav-text">{data.title}</span>
                        <span className="badge badge-danger badge-xs ms-1">{data.update}</span>
                      </Link>
                      <Collapse in={state.active === data.title ? true : false}>
                        <ul className={`${menuClass === "mm-collapse" ? "mm-show" : ""}`}>
                          {data.content && data.content.map((data, index) => {
                            return (
                              <li key={index}
                                className={`${state.activeSubmenu === data.title ? "mm-active" : ""}`}
                              >
                                {data.content && data.content.length > 0 ?
                                  <>
                                    <Link to={data.to} className={data.hasMenu ? 'has-arrow' : ''}
                                      onClick={() => { handleSubmenuActive(data.title) }}
                                    >
                                      {data.title}
                                    </Link>
                                    <Collapse in={state.activeSubmenu === data.title ? true : false}>
                                      <ul className={`${menuClass === "mm-collapse" ? "mm-show" : ""}`}>
                                        {data.content && data.content.map((data, index) => {
                                          return (
                                            <li key={index}>
                                              <Link className={`${path === data.to ? "mm-active" : ""}`} to={data.to}>{data.title}</Link>
                                            </li>
                                          )
                                        })}
                                      </ul>
                                    </Collapse>
                                  </>
                                  :
                                  <Link to={data.to}>
                                    {data.title}
                                  </Link>
                                }

                              </li>
                            )
                          })}
                        </ul>
                      </Collapse>
                    </>
                    :
                    <Link to={data.to} >
                      {data.iconStyle}
                      <span className="nav-text">{data.title}</span>
                    </Link>
                  }

                </li>
              )
            }
          })}

          <li className="menu-title">Account</li>
          <li>
            <Link to="#" onClick={(e) => { e.preventDefault(); onLogout(); }}>
              <i className="fa fa-sign-out"></i>
              <span className="nav-text">Logout</span>
            </Link>
          </li>
        </ul>
        <div className="copyright">
          <p><strong>IRCUB</strong> © {year} Farsight Africa POC</p>
          <p className="fs-12">Demo UI based on Dompet <span className="heart" onClick={heartBlast}></span></p>
        </div>
      </PerfectScrollbar>
    </div>
  );
};

export default SideBar;
