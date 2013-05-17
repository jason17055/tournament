package dragonfin.tournament;

import java.awt.*;
import java.awt.event.*;
import javax.swing.*;

public class PlayParticipantsDialog extends JDialog
{
	public PlayParticipantsDialog(Window owner)
	{
		super(owner, "Edit Participants", Dialog.ModalityType.DOCUMENT_MODAL);

		JButton fooBtn = new JButton("FOO!");
		getContentPane().add(fooBtn, BorderLayout.CENTER);

		JPanel buttonPane = new JPanel();
		getContentPane().add(buttonPane, BorderLayout.SOUTH);

		JButton btn1 = new JButton("OK");
		btn1.addActionListener(new ActionListener() {
			public void actionPerformed(ActionEvent evt) {
				onOkClicked();
			}});
		buttonPane.add(btn1);

		JButton btn2 = new JButton("Cancel");
		btn2.addActionListener(new ActionListener() {
			public void actionPerformed(ActionEvent evt) {
				dispose();
			}});
		buttonPane.add(btn2);

		pack();
		setDefaultCloseOperation(WindowConstants.DISPOSE_ON_CLOSE);
		setLocationRelativeTo(owner);
	}

	private void onOkClicked()
	{
		dispose();
	}
}
