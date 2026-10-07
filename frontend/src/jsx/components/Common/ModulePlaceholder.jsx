import React from 'react';
import { Link } from 'react-router-dom';

const ModulePlaceholder = ({ title, moduleLabel = 'a later module', description }) => {
  return (
    <>
      <div className="page-titles">
        <ol className="breadcrumb">
          <li className="breadcrumb-item"><Link to="/dashboard">Dashboard</Link></li>
          <li className="breadcrumb-item active"><Link to="#">{title}</Link></li>
        </ol>
      </div>
      <div className="row">
        <div className="col-xl-8">
          <div className="card">
            <div className="card-header">
              <h4 className="card-title mb-0">{title}</h4>
            </div>
            <div className="card-body">
              <div className="alert alert-info mb-3">
                This screen belongs to <strong>{moduleLabel}</strong> of the IRCUB POC.
              </div>
              <p className="mb-3">
                {description || 'The page is registered so navigation does not break. Implementation comes next.'}
              </p>
              <Link to="/payers" className="btn btn-primary btn-sm me-2">Go to Payer Registry</Link>
              <Link to="/dashboard" className="btn btn-outline-secondary btn-sm">Back to Dashboard</Link>
            </div>
          </div>
        </div>
      </div>
    </>
  );
};

export default ModulePlaceholder;
