// Copyright (c) 2014 The a5a developers
// Distributed under the MIT/X11 software license, see the accompanying
// file COPYING or http://www.opensource.org/licenses/mit-license.php.

#include <stdio.h>
#include <float.h>
#include <limits.h>
#include <math.h>
#include <stdio.h>
#include <stdlib.h>
#include <stdint.h>
//#include <gmpxx.h>
#include "a5amath.h"

//#define EPS1 (std::numeric_limits<double>::epsilon())
#define EPS1 (DBL_EPSILON)
#define EPS2 3.0e-11

double exp_n(double xt)
{
    double p1 = -700.0, p3 = -0.8e-8, p4 = 0.8e-8, p6 = 700.0;
    if(xt < p1)
        return 0;
    else if(xt > p6)
        return 1e200;
    else if(xt > p3 && xt < p4)
        return (1.0 + xt);
    else
        return exp(xt);
}

// 1 / (1 + exp(x1-x2))
double exp_n2(double x1, double x2)
{
    double p1 = -700., p2 = -37., p3 = -0.8e-8, p4 = 0.8e-8, p5 = 37., p6 = 700.;
    double xt = x1 - x2;
    if (xt < p1+1.e-200)
        return 1.;
    else if (xt > p1 && xt < p2 + 1.e-200)
        return ( 1. - exp(xt) );
    else if (xt > p2 && xt < p3 + 1.e-200)
        return ( 1. / (1. + exp(xt)) );
    else if (xt > p3 && xt < p4)
        return ( 1. / (2. + xt) );
    else if (xt > p4 - 1.e-200 && xt < p5)
        return ( exp(-xt) / (1. + exp(-xt)) );
    else if (xt > p5 - 1.e-200 && xt < p6)
        return ( exp(-xt) );
    else if (xt > p6 - 1.e-200)
        return 0.;
}

void gauleg(double x1, double x2, double x[], double w[], int n)
{
    int m,j,i;
    double z1, z, xm, xl, pp, p3, p2, p1;
    m=(n+1)/2;
    xm=0.5*(x2+x1);
    xl=0.5*(x2-x1);
    for (i=1;i<=m;i++) {
	    z=cos(3.141592654*(i-0.25)/(n+0.5));
	    do {
		    p1=1.0;
		    p2=0.0;
		    for (j=1;j<=n;j++) {
			    p3=p2;
			    p2=p1;
			    p1=((2.0*j-1.0)*z*p2-(j-1.0)*p3)/j;
		    }
		    pp=n*(z*p1-p2)/(z*z-1.0);
		    z1=z;
		    z=z1-p1/pp;
	    } while (fabs(z-z1) > EPS2);
	    x[i]=xm-xl*z;
	    x[n+1-i]=xm+xl*z;
	    w[i]=2.0*xl/((1.0-z*z)*pp*pp);
	    w[n+1-i]=w[i];
    }
}

double GaussianQuad_N(double func(const double), const double a2, const double b2, int NptGQ)
{
    double s=0.0;
    double x[NptGQ], w[NptGQ];
    int j;
//    double dh=(b2-a2)/double(divs);
    gauleg(a2, b2, x, w, NptGQ);
    for (j=1; j<=NptGQ; j++) {
      s += w[j]*func(x[j]);
    }
/*
    for (i=1; i<=divs; i++)
    {
	a0 = a2 + (i-1)*dh;
	b0 = a0 + dh;
	gauleg(a0, b0, x, w, NptGQ);
	for (j=1; j<=NptGQ; j++)
	{
	  s += w[j]*func(x[j]);
	}
    }
*/
    return s;
}

double swit_(double wvnmb)
{
    return pow( (5.55243*(exp_n(-0.3*wvnmb/15.762) - exp_n(-0.6*wvnmb/15.762)))*wvnmb, 0.5)
	  / 1034.66 * pow(sin(wvnmb/65.), 2.);
}

uint32_t sw_(int nnounce, int divs)
{
    double wmax = ((sqrt((double)(nnounce))*(1.+EPS1))/450+100);
    return ((uint32_t)(GaussianQuad_N(swit_, 0., wmax, divs)*(1.+EPS1)*1.e6));
}