import React, { Fragment } from "react";
import SideBar from "./SideBar";
import NavHader from "./NavHader";
import Header from "./Header";

const JobieNav = () => {
  return (
    <Fragment>
      <NavHader />
      <Header />
      <SideBar />
    </Fragment>
  );
};

export default JobieNav;
