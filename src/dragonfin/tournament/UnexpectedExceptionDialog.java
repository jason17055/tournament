package dragonfin.tournament;

import java.awt.*;
import java.io.*;
import java.util.*;
import javax.swing.*;

public class UnexpectedExceptionDialog
{
	//this prevents the class from being instantiated
	private UnexpectedExceptionDialog() { }

	static ResourceBundle strings = MainWindow.strings;

	public static void showException(Component owner, Throwable e)
	{
		StringWriter w = new StringWriter();
		e.printStackTrace(new PrintWriter(w));

		JTextPane stackTracePane = new JTextPane();
		stackTracePane.setEditable(false);
		stackTracePane.setText(w.toString());

		final JScrollPane detailsPane = new JScrollPane(stackTracePane);
		detailsPane.setVerticalScrollBarPolicy(JScrollPane.VERTICAL_SCROLLBAR_ALWAYS);
		detailsPane.setPreferredSize(new Dimension(480,240));
		detailsPane.setMinimumSize(new Dimension(0,0));

		int rv = JOptionPane.showOptionDialog(owner, e,
			strings.getString("main.error_unexpected"),
			JOptionPane.DEFAULT_OPTION,
			JOptionPane.ERROR_MESSAGE,
			null,
			new String[] {
				strings.getString("error_show_stacktrace"),
				strings.getString("error_close")
				},
			1
			);
		if (rv == 0)
		{
			JOptionPane.showMessageDialog(owner, detailsPane,
				strings.getString("main.error_unexpected"),
				JOptionPane.ERROR_MESSAGE);
		}
	}
}
