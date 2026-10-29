import type {Metadata} from 'next';
import './globals.css';
export const metadata:Metadata={title:'Meda Pass — Less planning. More play.',description:'Find your next home ground. A working Meda Pass web sandbox for sports field discovery, reservations, and field management in Addis Ababa.',icons:{icon:'/favicon.svg',shortcut:'/favicon.svg'}};
export default function RootLayout({children}:{children:React.ReactNode}){return <html lang="en"><body>{children}</body></html>;}
