import React, { useState } from 'react'
import { connect, useDispatch } from 'react-redux';
import { Link, useNavigate } from 'react-router-dom'
import {
  loadingToggleAction, loginAction,
} from '../../store/actions/AuthActions';

// image
import logo from "../../assets/images/logo-full-white.png";
import loginbg from "../../assets/images/bg-login.jpg";

const isDev = Boolean(import.meta.env.DEV);

function Login(props) {
  let year = new Date().getFullYear();
  const [email, setEmail] = useState(isDev ? 'admin@ircub.test' : '');
  let errorsObj = { email: '', password: '', otp: '' };
  const [errors, setErrors] = useState(errorsObj);
  const [password, setPassword] = useState(isDev ? 'Password@123' : '');
  const [otp, setOtp] = useState('');
  const needs2fa = Boolean(props.errorMessage?.requires_2fa);

  const dispatch = useDispatch();
  const nav = useNavigate();

  function onLogin(e) {
    e.preventDefault();
    let error = false;
    const errorObj = { ...errorsObj };
    if (email === '') {
      errorObj.email = 'Email is Required';
      error = true;
    }
    if (password === '') {
      errorObj.password = 'Password is Required';
      error = true;
    }
    if (needs2fa && otp === '') {
      errorObj.otp = 'OTP is required for this account';
      error = true;
    }
    setErrors(errorObj);
    if (error) {
      return;
    }
    dispatch(loadingToggleAction(true));
    dispatch(loginAction(email, password, nav, needs2fa ? otp : null));
  }

  return (
    <div className="login-main-page" style={{ backgroundImage: "url(" + loginbg + ")" }}>
      <div className="login-wrapper">
        <div className="login-aside-left" >
          <Link to="/login" className="login-logo">
            <img src={logo} alt="" />
          </Link>
          <div className="login-description">
            <h2 className="main-title mb-2">Welcome To IRCUB</h2>
            <p className="">Integrated Revenue Collection & Utility Billing Platform — Farsight Africa. Sign in with your assigned role account.</p>
            <div className="mt-5 bottom-privacy">
              <span className="text-white-50">© {year} IRCUB · Farsight Africa</span>
            </div>
          </div>
        </div>
        <div className="login-aside-right">
          <div className="row m-0 justify-content-center h-100 align-items-center">
            <div className="p-5">
              <div className="authincation-content">
                <div className="row no-gutters">
                  <div className="col-xl-12">
                    <div className="auth-form-1">
                      <div className="mb-4">
                        <h3 className="dz-title mb-1">Sign in</h3>
                        {isDev ? (
                          <p className="text-muted small mb-0">Local demo credentials are prefilled for development only.</p>
                        ) : (
                          <p className="">Use the credentials issued by your administrator.</p>
                        )}
                      </div>
                      {props.errorMessage && (
                        <div className='bg-red-300 text-red-900 border border-red-900 p-1 my-2'>
                          {typeof props.errorMessage === 'string' ? props.errorMessage : props.errorMessage.message}
                        </div>
                      )}
                      {props.successMessage && (
                        <div className='bg-green-300 text-green-900 border border-green-900 p-1 my-2'>
                          {props.successMessage}
                        </div>
                      )}
                      <form onSubmit={onLogin}>
                        <div className="form-group">
                          <label className="mb-2 ">
                            <strong>Email</strong><span className='required'> *</span>
                          </label>
                          <input type="email" className="form-control"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            placeholder="Type Your Email Address"
                            autoComplete="username"
                          />
                          {errors.email && <div className="text-danger fs-12">{errors.email}</div>}
                        </div>
                        <div className="form-group">
                          <label className="mb-2 "><strong>Password</strong> <span className='required'> *</span></label>
                          <input
                            type="password"
                            className="form-control"
                            value={password}
                            placeholder="Type Your Password"
                            autoComplete="current-password"
                            onChange={(e) =>
                              setPassword(e.target.value)
                            }
                          />
                          {errors.password && <div className="text-danger fs-12">{errors.password}</div>}
                          <small className="text-muted">Policy: min 10 chars with upper, lower, number, symbol.</small>
                        </div>
                        {needs2fa && (
                          <div className="form-group">
                            <label className="mb-2"><strong>2FA OTP</strong> <span className='required'> *</span></label>
                            <input
                              type="text"
                              className="form-control"
                              value={otp}
                              placeholder="One-time code"
                              onChange={(e) => setOtp(e.target.value)}
                              autoComplete="one-time-code"
                            />
                            {errors.otp && <div className="text-danger fs-12">{errors.otp}</div>}
                          </div>
                        )}
                        <div className="text-center mt-4">
                          <button
                            type="submit"
                            className="btn btn-primary btn-block"
                          >
                            Sign In
                          </button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

const mapStateToProps = (state) => {
  return {
    errorMessage: state.auth.errorMessage,
    successMessage: state.auth.successMessage,
    showLoading: state.auth.showLoading,
  };
};

export default connect(mapStateToProps)(Login);
